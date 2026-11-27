<?php

use App\Domain\Administration\Services\FeatureFlags;
use App\Domain\Administration\Services\Settings;
use App\Domain\Questions\Actions\UpdateQuestion;
use App\Domain\Questions\DTO\QuestionData;
use App\Models\Category;
use App\Models\Question;
use Livewire\Livewire;

it('lets a verified user ask a question that waits for approval by default', function () {
    $user = member();
    Livewire::actingAs($user)->test('pages::questions.create')
        ->set('text', 'Co byste vrátili z doby před mobily?')
        ->set('category_id', Category::first()->id)
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $q = Question::first();
    expect($q->author_id)->toBe($user->id)->and($q->moderation_status->value)->toBe('pending')->and($q->versions()->count())->toBe(1);
    // Not public yet, but the author can see it.
    auth()->logout();
    $this->get($q->url())->assertForbidden();
    $this->actingAs($user)->get($q->url())->assertOk()->assertSee('vidíš jen ty');
});

it('publishes community questions directly when approval is off', function () {
    app(Settings::class)->set('questions.user_questions_need_approval', false);
    Livewire::actingAs(member())->test('pages::questions.create')
        ->set('text', 'Kterou věc v bytě byste nikdy nevyhodili?')->set('category_id', Category::first()->id)->call('save');
    $this->get(Question::first()->url())->assertOk();
});

it('validates question text', function () {
    Livewire::actingAs(member())->test('pages::questions.create')
        ->set('text', 'Krátké')->set('category_id', Category::first()->id)->call('save')->assertHasErrors('text');
    Livewire::actingAs(member())->test('pages::questions.create')
        ->set('text', 'Tohle není otázka bez otazníku')->set('category_id', Category::first()->id)->call('save')->assertHasErrors('text');
});

it('flags questions with links for moderation', function () {
    app(Settings::class)->set('questions.user_questions_need_approval', false);
    Livewire::actingAs(member())->test('pages::questions.create')
        ->set('text', 'Kupte si to levně na www.spam.cz, ne?')->set('category_id', Category::first()->id)->call('save');
    expect(Question::first()->moderation_status->value)->toBe('under_review');
});

it('requires a verified e-mail to ask', function () {
    $this->actingAs(member(null, ['email_verified_at' => null]))->get('/otazky/nova')->assertRedirect(route('verification.notice'));
});

it('versions question edits', function () {
    $q = liveQuestion(['text' => 'Původní znění otázky?']);
    app(UpdateQuestion::class)(member('editor'), $q, new QuestionData('Nové znění otázky?', $q->category_id), 'překlep');
    $q->refresh();
    expect($q->version)->toBe(2)->and($q->text)->toBe('Nové znění otázky?')
        ->and($q->versions()->pluck('text')->all())->toBe(['Nové znění otázky?', 'Původní znění otázky?']);
});

it('lists, filters and shows questions', function () {
    $q = liveQuestion(['text' => 'Co by mělo stát míň, než stojí?']);
    $this->get('/otazky')->assertOk()->assertSee($q->text);
    $this->get('/otazky?razeni=bez-odpovedi')->assertOk()->assertSee($q->text);
    $this->get($q->url())->assertOk()->assertSee($q->text)->assertSee('Buď první, kdo odpoví.');
    $this->get('/kategorie')->assertOk()->assertSee('Peníze');
    $this->get(route('categories.show', $q->category))->assertOk()->assertSee($q->text);
    $this->get('/objevovat')->assertOk();
    $this->get('/nahodna-otazka')->assertRedirect($q->url());
});

it('hides drafts from the public', function () {
    $q = Question::factory()->create();
    $this->get($q->url())->assertForbidden();
    $this->get('/otazky')->assertDontSee($q->text);
});

it('returns 404 when the module is disabled', function () {
    app(FeatureFlags::class)->set('questions', false);
    $this->get('/otazky')->assertNotFound();
});
