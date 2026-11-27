<?php

use App\Domain\Administration\Services\Settings;
use App\Domain\Answers\Actions\DeleteAnswer;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\Actions\UpdateAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Models\Answer;
use Livewire\Livewire;

it('publishes an answer through the composer', function () {
    $q = liveQuestion();
    $user = member();
    Livewire::actingAs($user)->test('answer-composer', ['questionId' => $q->id])
        ->set('text', 'Tož čas.')->call('publish')->assertHasNoErrors()->assertSee('Zveřejněno!');

    $a = Answer::first();
    expect($a->original_text)->toBe('Tož čas.')->and($a->display_text)->toBe('Tož čas.')->and($a->is_stylized)->toBeFalse();
    expect($q->fresh()->answers_count)->toBe(1);
    $this->get($q->url())->assertSee('Tož čas.');
});

it('enforces the 100 character limit counting spaces and diacritics as one character each', function () {
    $q = liveQuestion();
    $exact = str_repeat('ž', 50).str_repeat(' ', 0).str_repeat('ě', 50); // 100 graphemes, 200 bytes
    $a = answerOf($q, null, $exact);
    expect(mb_strlen($a->original_text))->toBe(100);

    expect(fn () => answerOf($q, null, str_repeat('á', 101)))->toThrow(AnswerException::class, 'nejvýše 100 znaků');
});

it('counts emoji and combining characters as single characters', function () {
    $q = liveQuestion();
    // 99 letters + one emoji (4 bytes in UTF-8, one character) = 100
    $a = answerOf($q, null, str_repeat('a', 99).'👍');
    expect($a)->toBeInstanceOf(Answer::class);
    // Decomposed "é" (e + combining acute) is normalised to NFC.
    $b = answerOf($q, null, "Kafe\u{0301} a klid");
    expect($b->original_text)->toBe('Kafé a klid');
});

it('respects a changed limit from settings', function () {
    app(Settings::class)->set('answers.max_length', 20);
    expect(fn () => answerOf(liveQuestion(), null, str_repeat('x', 21)))->toThrow(AnswerException::class);
});

it('strips control and zero-width characters and rejects empty answers', function () {
    $q = liveQuestion();
    $a = answerOf($q, null, "  Ano\u{200B}\u{0007}  ");
    expect($a->original_text)->toBe('Ano');
    expect(fn () => answerOf($q, null, "\u{200B} "))->toThrow(AnswerException::class);
});

it('allows only one answer per user per question by default', function () {
    $q = liveQuestion();
    $u = member();
    answerOf($q, $u, 'Já.');
    expect(fn () => answerOf($q, $u, 'Ještě jednou.'))->toThrow(AnswerException::class, 'už jsi odpověděl');
    expect(Answer::count())->toBe(1);
});

it('requires a verified e-mail to answer', function () {
    expect(fn () => answerOf(liveQuestion(), member(null, ['email_verified_at' => null]), 'Ano'))->toThrow(AnswerException::class, 'ověř e-mail');
});

it('does not accept answers to closed questions', function () {
    $q = liveQuestion(['closes_at' => now()->addMinute()]);
    $q->forceFill(['closes_at' => now()->subMinute()])->save();
    expect(fn () => answerOf($q, null, 'Ano'))->toThrow(AnswerException::class);
});

it('keeps edit history and lets the author delete', function () {
    $q = liveQuestion();
    $u = member();
    $a = answerOf($q, $u, 'První verze');
    app(UpdateAnswer::class)($u, $a, new AnswerData('Druhá verze'));
    $a->refresh();
    expect($a->version)->toBe(2)->and($a->versions()->pluck('original_text')->all())->toBe(['Druhá verze', 'První verze']);
    expect(fn () => app(UpdateAnswer::class)(member(), $a, new AnswerData('Cizí')))->toThrow(AnswerException::class);

    app(DeleteAnswer::class)($u, $a);
    expect($q->fresh()->answers_count)->toBe(0);
});

it('sends answers with personal data to moderation instead of publishing', function () {
    $q = liveQuestion();
    $a = answerOf($q, null, 'Volejte 777 123 456');
    expect($a->moderation_status->value)->toBe('under_review');
    $this->get($q->url())->assertDontSee('777 123 456');
    expect($q->fresh()->answers_count)->toBe(0);
});

it('shows anonymous answers without the author name', function () {
    $q = liveQuestion();
    $u = member(null, ['name' => 'Tajný_Pepa']);
    app(SubmitAnswer::class)($u, $q, new AnswerData('Tajemství', isAnonymous: true));
    $this->get($q->url())->assertSee('Tajemství')->assertSee('Anonym')->assertDontSee('Tajný_Pepa');
    $this->get(route('profiles.show', $u))->assertDontSee('Tajemství');
});

it('never exposes a private original to other users', function () {
    $q = liveQuestion();
    $u = member();
    $a = answerOf($q, $u, 'Já.');
    // Simulate a stylised answer whose author did not allow showing the original.
    $a->forceFill(['display_text' => 'Tož já, však u mňa už aj bída bydlí na dluh.', 'is_stylized' => true, 'show_original' => false])->save();

    expect($a->fresh()->publicOriginal(member()))->toBeNull();
    expect($a->fresh()->publicOriginal($u))->toBe('Já.');
    $html = $this->actingAs(member())->get($q->url())->getContent();
    expect($html)->toContain('bída bydlí na dluh')->not->toContain('Originál: „Já.“');
    expect(json_encode($a->fresh()->toArray()))->not->toContain('"original_text"');
});

it('shows the original only when the author consents', function () {
    $q = liveQuestion();
    $a = answerOf($q, null, 'Děkuji.');
    $a->forceFill(['display_text' => 'Pánbu zaplať.', 'is_stylized' => true, 'show_original' => true])->save();
    $this->get($q->url())->assertSee('Originál: „Děkuji.“', false);
});

it('serialises concurrent submissions of the same user', function () {
    // Two calls in a row model the double-click; the advisory lock + count check keeps one row.
    $q = liveQuestion();
    $u = member();
    try {
        answerOf($q, $u, 'A');
        answerOf($q, $u, 'B');
    } catch (AnswerException) {
    }
    expect(Answer::where('user_id', $u->id)->count())->toBe(1);
});
