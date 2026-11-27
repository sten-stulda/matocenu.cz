<?php

use App\Domain\Identity\Actions\DeleteAccount;
use App\Domain\Identity\Services\PersonalDataExporter;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Needs\Actions\InterestActions;
use App\Domain\Needs\Actions\NeedActions;
use App\Domain\Needs\Actions\OfferActions;
use App\Domain\Needs\Enums\InterestKind;
use App\Domain\Needs\Exceptions\NeedException;
use App\Domain\Needs\Services\NeedStats;
use App\Models\Category;
use App\Models\Location;
use App\Models\Need;
use App\Models\NeedOffer;
use App\Models\User;
use App\Notifications\NeedOfferMessage;
use App\Notifications\NeedOfferReceived;
use Illuminate\Support\Facades\Notification;

function needRegion(string $name = 'Ústecký kraj'): Location
{
    $cz = Location::firstOrCreate(['type' => 'country', 'code' => 'CZ'], ['name' => 'Česko', 'slug' => 'cesko']);

    return Location::firstOrCreate(['type' => 'region', 'code' => substr(md5($name), 0, 12)], ['parent_id' => $cz->id, 'name' => $name, 'slug' => Str::slug($name)]);
}

function needOf(?User $author = null, array $data = []): Need
{
    return app(NeedActions::class)->create($author ?? member(), $data + [
        'title' => 'Country klub v Ústí nad Labem',
        'description' => 'Chybí tu místo, kde by se dalo zatancovat a poslechnout country.',
        'category_id' => Category::where('is_active', true)->value('id'),
        'type' => 'missing_service',
        'location_id' => null,
        'urgency' => 'medium',
    ]);
}

function payer(Need $need, string $amount, ?Location $loc = null, bool $consent = false): User
{
    $u = member(null, $loc ? ['location_id' => $loc->id] : []);
    app(InterestActions::class)->set($u, $need, InterestKind::WouldPay, ['amount' => $amount, 'frequency' => 'monthly', 'contact_consent' => $consent]);

    return $u;
}

it('creates a public need with unique slug and history', function () {
    $a = needOf();
    $b = needOf();
    expect($a->isPubliclyVisible())->toBeTrue()
        ->and($b->slug)->not->toBe($a->slug)
        ->and(DB::table('need_status_history')->where('need_id', $a->id)->value('to_status'))->toBe('open');
    $this->get(route('needs.index'))->assertOk()->assertSee('Country klub');
    $this->get($a->url())->assertOk()->assertSee('Country klub');
});

it('counts each kind of interest once per person and ignores the author', function () {
    $author = member();
    $need = needOf($author);
    $u = member();
    app(InterestActions::class)->set($u, $need, InterestKind::NeedToo);
    app(InterestActions::class)->set($u, $need, InterestKind::NeedToo);
    app(InterestActions::class)->set($u, $need, InterestKind::Like);
    $need->refresh();
    expect($need->need_too_count)->toBe(1)->and($need->interested_count)->toBe(1);
    expect(fn () => app(InterestActions::class)->set($author, $need, InterestKind::NeedToo))->toThrow(NeedException::class);
});

it('keeps willingness to pay separate from commitment and validates the amount', function () {
    $need = needOf();
    $u = member();
    $i = app(InterestActions::class)->set($u, $need, InterestKind::WouldPay, ['amount' => '250,50', 'currency' => 'CZK']);
    expect($i->amount)->toBe('250.50')->and($i->is_commitment)->toBeFalse();
    expect(fn () => app(InterestActions::class)->set(member(), $need, InterestKind::WouldPay, ['amount' => 'hodně']))->toThrow(NeedException::class);
    // Amount is ignored for non-paying interest kinds.
    expect(app(InterestActions::class)->set(member(), $need, InterestKind::Like, ['amount' => '100'])->amount)->toBeNull();
});

it('shows amounts and regions only above k-anonymity thresholds', function () {
    $need = needOf();
    $region = needRegion();
    foreach (['100', '200', '300', '400'] as $a) {
        payer($need, $a, $region);
    }
    $s = app(NeedStats::class)->forNeed($need);
    expect($s['amounts_shown'])->toBeFalse()->and($s['median'])->toBeNull()
        ->and($s['regions'])->toHaveCount(1)->and($s['regions'][0]['count'])->toBe(4);

    payer($need, '500');
    $s = app(NeedStats::class)->forNeed($need);
    expect($s['amounts_shown'])->toBeTrue()->and($s['median'])->toBe(300.0)->and($s['monthly_estimate'])->toBe(1500.0);

    $other = needOf();
    payer($other, '100', needRegion('Liberecký kraj'));
    expect(app(NeedStats::class)->forNeed($other)['regions'])->toBe([]);
});

it('lets providers offer and relays messages only to consenting people', function () {
    Notification::fake();
    $author = member();
    $need = needOf($author);
    $yes = payer($need, '100', consent: true);
    $no = payer($need, '100');
    $provider = member();
    $offer = app(OfferActions::class)->create($provider, $need, 'Otevřu klub ve sklepě, potřebuju aspoň 30 lidí.', '150 Kč / návštěva');
    Notification::assertSentTo($author, NeedOfferReceived::class);
    expect($need->fresh()->offers_count)->toBe(1);

    expect(app(OfferActions::class)->broadcast($provider, $offer, 'Ahoj, první večer bude v pátek, přijďte!'))->toBe(1);
    Notification::assertSentTo($yes, NeedOfferMessage::class);
    Notification::assertNotSentTo($no, NeedOfferMessage::class);
    // Cooldown
    expect(fn () => app(OfferActions::class)->broadcast($provider, $offer->fresh(), 'Ještě jedna zpráva pro vás.'))->toThrow(NeedException::class);
    // Author cannot offer on own need, providers only once.
    expect(fn () => app(OfferActions::class)->create($author, $need, 'Vyřeším si to sám, žádný problém.', null))->toThrow(NeedException::class);
    expect(fn () => app(OfferActions::class)->create($provider, $need, 'Druhá nabídka od stejného člověka.', null))->toThrow(NeedException::class);
});

it('moves the need to in progress when the author accepts an offer', function () {
    $author = member();
    $need = needOf($author);
    $offer = app(OfferActions::class)->create(member(), $need, 'Mám prostory a zkušenosti s pořádáním akcí.', null);
    expect(fn () => app(OfferActions::class)->decide(member(), $offer, true))->toThrow(NeedException::class);
    app(OfferActions::class)->decide($author, $offer, true);
    expect($offer->fresh()->status)->toBe('accepted')->and($need->fresh()->status)->toBe('in_progress');
});

it('hides needs rejected by moderation', function () {
    $need = needOf();
    app(Moderation::class)->transition($need, 'reject', ModerationStatus::Rejected, member('admin'), 'spam');
    $this->get($need->url())->assertNotFound();
    $this->get(route('needs.index'))->assertDontSee('Country klub');
});

it('shows aggregated opportunities without personal data', function () {
    $need = needOf();
    $u = payer($need, '100');
    $this->get(route('opportunities.index'))->assertOk()->assertSee('Country klub')->assertDontSee($u->email);
});

it('exports and erases personal data about needs', function () {
    $author = member();
    $need = needOf($author);
    $u = payer($need, '120', consent: true);
    $export = app(PersonalDataExporter::class)->export($u);
    expect(json_encode($export))->toContain('Country klub');

    app(DeleteAccount::class)($u, false);
    expect(DB::table('need_interests')->where('user_id', $u->id)->count())->toBe(0)
        ->and($need->fresh()->would_pay_count)->toBe(0);

    app(DeleteAccount::class)($author, true);
    expect(Need::find($need->id))->toBeNull();
});

it('lets admins manage needs', function () {
    needOf();
    $this->actingAs(member('admin'))->get(route('admin.needs'))->assertOk()->assertSee('Country klub');
    $this->actingAs(member('admin'))->get(route('admin.opportunities'))->assertOk();
    $this->actingAs(member())->get(route('admin.needs'))->assertForbidden();
});

it('creates a need through the form', function () {
    $u = member();
    Livewire\Livewire::actingAs($u)->test('pages::needs.create')
        ->set('title', 'Spolehlivý řemeslník do 14 dnů')
        ->set('description', 'Sehnat instalatéra trvá měsíc, a to je ve městě.')
        ->set('category_id', Category::where('is_active', true)->value('id'))
        ->call('save')->assertHasNoErrors();
    expect(Need::where('user_id', $u->id)->count())->toBe(1);
    $this->actingAs($u)->get(route('needs.create'))->assertOk();
});

it('drives interest, offers and status from the need page', function () {
    $author = member();
    $need = needOf($author);
    $u = member();
    Livewire\Livewire::actingAs($u)->test('need-interest', ['needId' => $need->id])
        ->call('toggle', 'need_too')
        ->set('amount', '300')->set('frequency', 'per_visit')->set('consent', true)->call('savePay')
        ->assertSet('error', null);
    expect($need->fresh()->would_pay_count)->toBe(1)->and($need->fresh()->need_too_count)->toBe(1);

    $p = member();
    Livewire\Livewire::actingAs($p)->test('need-offers', ['needId' => $need->id])
        ->set('description', 'Mám sál pro 80 lidí a kapelu, stačí se domluvit.')->call('create')->assertSet('error', null);
    $offer = NeedOffer::firstOrFail();
    Livewire\Livewire::actingAs($author)->test('need-offers', ['needId' => $need->id])->call('decide', $offer->id, true);
    expect($need->fresh()->status)->toBe('in_progress');

    Livewire\Livewire::actingAs($author)->test('need-manage', ['needId' => $need->id])
        ->set('status', 'solved')->set('note', 'Klub otevřen')->call('save');
    expect($need->fresh()->status)->toBe('solved');

    $this->actingAs($u)->get($need->url())->assertOk();
    $this->actingAs($author)->get($need->url())->assertOk();
});

it('lets admins hide needs and offers', function () {
    $need = needOf();
    Livewire\Livewire::actingAs(member('admin'))->test('pages::admin.needs')->call('hide', 'need', $need->id);
    expect($need->fresh()->isPubliclyVisible())->toBeFalse();
});
