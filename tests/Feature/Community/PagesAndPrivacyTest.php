<?php

use App\Domain\Identity\Actions\DeleteAccount;
use App\Domain\Identity\Services\PersonalDataExporter;
use App\Models\Answer;

it('renders the homepage sections with real content only', function () {
    $q = liveQuestion(['text' => 'Jaký zvuk vám připomíná dětství?']);
    answerOf($q, null, 'Cinkání lžičky.');
    $this->get('/')->assertOk()->assertSee('Otázka dne se právě vaří.')->assertSee('Jaký zvuk vám připomíná dětství?');
});

it('shows a public profile without anonymous content, bookmarks only to the owner', function () {
    $u = member(null, ['name' => 'Bára_Ostrava']);
    $q = liveQuestion();
    answerOf($q, $u, 'Spaní. Osm hodin v kuse.');
    $this->get(route('profiles.show', $u))->assertOk()->assertSee('Bára_Ostrava')->assertSee('Spaní. Osm hodin v kuse.')->assertDontSee('Oblíbené');
    $this->actingAs($u)->get(route('profiles.show', $u).'?tab=oblibene')->assertOk()->assertSee('Oblíbené');
});

it('exports and anonymises community content on account deletion', function () {
    $u = member();
    $a = answerOf(liveQuestion(), $u, 'Moje odpověď');
    $export = app(PersonalDataExporter::class)->export($u);
    expect($export['komunita']['odpovedi'][0]['original'])->toBe('Moje odpověď');

    app(DeleteAccount::class)($u, false);
    expect(Answer::find($a->id)->is_anonymous)->toBeTrue();
});

it('deletes content when the user asks for it', function () {
    $u = member();
    $a = answerOf(liveQuestion(), $u, 'Smaž mě');
    app(DeleteAccount::class)($u, true);
    expect(Answer::find($a->id))->toBeNull();
});

it('renders admin pages for community management', function () {
    $this->actingAs(member('superadmin'));
    $q = liveQuestion();
    answerOf($q);
    foreach (['/admin', '/admin/otazky', '/admin/otazky/'.$q->id, '/admin/otazka-dne', '/admin/odpovedi', '/admin/komentare', '/admin/kategorie', '/admin/reakce', '/admin/moderace', '/admin/odvolani'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('shows notifications and the moderation page to the user', function () {
    $this->actingAs(member());
    $this->get('/ucet/upozorneni')->assertOk();
    $this->get('/ucet/moderace')->assertOk();
    $this->get('/ucet/profil')->assertRedirect();
});
