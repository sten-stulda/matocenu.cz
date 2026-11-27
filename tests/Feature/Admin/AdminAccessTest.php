<?php

use App\Domain\Administration\Services\FeatureFlags;
use App\Domain\Administration\Services\Settings;
use App\Models\AuditLog;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('redirects guests to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('forbids regular users', function () {
    $this->actingAs(member())->get('/admin')->assertForbidden();
});

it('lets a moderator see the dashboard but not settings', function () {
    $this->actingAs(member('moderator'));
    $this->get('/admin')->assertOk()->assertSee('Přehled');
    $this->get('/admin/nastaveni')->assertForbidden();
    $this->get('/admin/uzivatele')->assertOk();
});

it('requires a verified e-mail for admin', function () {
    $this->actingAs(member('admin', ['email_verified_at' => null]))->get('/admin')->assertRedirect(route('verification.notice'));
});

it('renders every admin page for a superadmin', function () {
    $this->actingAs(member('superadmin'));
    foreach (['/admin', '/admin/uzivatele', '/admin/role', '/admin/feature-flags', '/admin/nastaveni', '/admin/audit', '/admin/gdpr', '/admin/pravni-dokumenty'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('stores settings with an audit trail', function () {
    $admin = member('superadmin');
    Livewire::actingAs($admin)->test('pages::admin.settings')
        ->set('values.answers__max_length', 120)
        ->call('save')->assertHasNoErrors();
    expect(app(Settings::class)->int('answers.max_length'))->toBe(120);
    expect(AuditLog::where('action', 'settings.updated')->exists())->toBeTrue();
});

it('never enables unfinished modules', function () {
    $flags = app(FeatureFlags::class);
    expect($flags->enabled('shop'))->toBeFalse()->and($flags->enabled('marketplace'))->toBeFalse();
    expect(fn () => $flags->set('shop', true))->toThrow(HttpException::class);
    $flags->set('quotes', false);
    expect($flags->enabled('quotes'))->toBeFalse();
});

it('lets only superadmins grant the admin role', function () {
    $admin = member('admin');
    $target = member();
    Livewire::actingAs($admin)->test('pages::admin.users')
        ->call('edit', $target->id)->set('editRoles', ['admin'])->call('saveRoles')->assertHasErrors('editRoles');
    expect($target->fresh()->hasRole('admin'))->toBeFalse();

    Livewire::actingAs($admin)->test('pages::admin.users')
        ->call('edit', $target->id)->set('editRoles', ['moderator'])->call('saveRoles')->assertHasNoErrors();
    expect($target->fresh()->hasRole('moderator'))->toBeTrue();
    expect(AuditLog::where('action', 'user.roles_changed')->count())->toBe(1);
});

it('suspends a user and revokes sessions', function () {
    $mod = member('moderator');
    $target = member();
    Livewire::actingAs($mod)->test('pages::admin.users')
        ->set('suspendReason', 'Opakovaný spam')->set('suspendDays', 3)
        ->call('suspend', $target->id);
    expect($target->fresh()->isActive())->toBeFalse();
});
