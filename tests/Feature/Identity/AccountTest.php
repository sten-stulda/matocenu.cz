<?php

use App\Domain\Administration\ReferenceData\LocationsSyncer;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Location;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders account pages', function () {
    $this->actingAs(member());
    $this->get('/ucet/nastaveni')->assertOk()->assertSee('Profil');
    $this->get('/ucet/soukromi')->assertOk()->assertSee('Export osobních údajů');
    $this->withSession(['auth.password_confirmed_at' => time()])->get('/ucet/zabezpeceni')->assertOk()->assertSee('Aktivní relace');
});

it('updates nickname, region and avatar (re-encoded to webp)', function () {
    Storage::fake('public');
    app(LocationsSyncer::class)->sync();
    $region = Location::where('code', 'CZ072')->first();
    $user = member();

    Livewire::actingAs($user)->test('pages::account.settings')
        ->set('name', 'Pepa z Kroměříže')
        ->set('locationId', $region->id)
        ->set('avatar', UploadedFile::fake()->image('a.jpg', 400, 300))
        ->call('saveProfile')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('Pepa z Kroměříže')->and($user->location_id)->toBe($region->id);
    expect($user->avatar_path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($user->avatar_path);
});

it('saves privacy and notification preferences', function () {
    $user = member();
    Livewire::actingAs($user)->test('pages::account.settings')
        ->set('privacy.anonymous_by_default', true)
        ->set('notifications.daily_question.mail', true)
        ->call('savePrivacy');
    $prefs = $user->fresh()->preferences();
    expect($prefs->anonymous_by_default)->toBeTrue()
        ->and($prefs->wantsNotification('daily_question', 'mail', false))->toBeTrue();
});

it('exports personal data as JSON', function () {
    $user = member(null, ['name' => 'Exportér']);
    $res = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get('/ucet/export');
    $res->assertOk();
    $json = json_decode($res->streamedContent(), true);
    expect($json['ucet']['email'])->toBe($user->email)
        ->and($json['ucet']['prezdivka'])->toBe('Exportér')
        ->and($json['souhlasy'])->not->toBeEmpty();
});

it('requires password confirmation for export', function () {
    $this->actingAs(member())->get('/ucet/export')->assertRedirect(route('password.confirm'));
});

it('deletes (anonymises) the account', function () {
    $user = member();
    Livewire::actingAs($user)->test('pages::account.privacy')
        ->set('deletePassword', 'password')
        ->set('deleteConfirm', 'SMAZAT')
        ->call('deleteAccount')
        ->assertRedirect(route('home'));

    $user = User::find($user->id);
    expect($user->status)->toBe(UserStatus::Deleted)
        ->and($user->email)->toEndWith('@deleted.invalid')
        ->and($user->password)->toBeNull()
        ->and($user->consents()->count())->toBe(0)
        ->and($user->getRoleNames())->toBeEmpty();
    $this->assertGuest();
});

it('refuses account deletion with a wrong password', function () {
    $user = member();
    Livewire::actingAs($user)->test('pages::account.privacy')
        ->set('deletePassword', 'spatne')->set('deleteConfirm', 'SMAZAT')
        ->call('deleteAccount')->assertHasErrors('deletePassword');
    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('files a privacy request', function () {
    $user = member();
    Livewire::actingAs($user)->test('pages::account.privacy')
        ->set('requestType', 'rectification')->set('requestMessage', 'Prosím opravte mi lokalitu v exportu.')
        ->call('submitRequest')->assertHasNoErrors();
    expect(PrivacyRequest::where('user_id', $user->id)->first()->type)->toBe('rectification');
});
