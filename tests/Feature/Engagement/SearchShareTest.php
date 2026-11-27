<?php

use App\Models\Question;
use Illuminate\Support\Facades\Storage;

it('finds questions and answers without diacritics', function () {
    $q = liveQuestion(['text' => 'Co vám v životě nejvíce chybí?']);
    answerOf($q, null, 'Čas a spánek.');
    $this->get('/hledat?q=zivote')->assertOk()->assertSee('Co vám v životě nejvíce chybí?');
    $this->get('/hledat?q=spanek')->assertSee('Čas a spánek.');
    $this->get('/hledat?q=Peníze')->assertSee('Peníze'); // category
});

it('never searches private originals', function () {
    $q = liveQuestion();
    $a = answerOf($q, null, 'Tajnyoriginal');
    $a->forceFill(['display_text' => 'Veřejná stylizace', 'is_stylized' => true, 'show_original' => false])->save();
    $this->get('/hledat?q=tajnyoriginal')->assertDontSee('Veřejná stylizace');
});

it('handles hostile search input safely', function () {
    $this->get('/hledat?q='.urlencode("')) OR 1=1 --& | ! <script>"))->assertOk();
});

it('renders share cards as PNG and refuses hidden content', function () {
    Storage::fake('public');
    $q = liveQuestion(['text' => 'Kdo v tvém okolí potřebuje finančně pomoct?']);
    $a = answerOf($q, null, 'Tož já, však u mňa už aj bída bydlí na dluh.');
    $res = $this->get(route('cards.answer', $a));
    $res->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('cards.question', $q))->assertOk();
    $a->forceFill(['moderation_status' => 'hidden'])->save();
    $this->get(route('cards.answer', $a))->assertNotFound();
});

it('lists only public pages in the sitemap', function () {
    $live = liveQuestion(['text' => 'Veřejná otázka?']);
    $draft = Question::factory()->create(['text' => 'Koncept otázky?']);
    $this->get('/sitemap.xml')->assertOk()->assertSee($live->slug)->assertDontSee($draft->slug)->assertDontSee('/admin');
});

it('renders admin pages of stage D', function () {
    $this->actingAs(member('superadmin'));
    $this->get('/admin/zebricky')->assertOk();
    $this->get('/admin/hlasky')->assertOk();
});
