<?php

use App\Domain\Questions\Exceptions\QuestionException;
use App\Domain\Questions\Services\DailyQuestions;
use App\Models\DailyQuestion;
use App\Models\Question;
use App\Notifications\DailyQuestionReady;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('publishes an approved daily question at the configured time and not before', function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-12-01 05:30', 'Europe/Prague'));
    $editor = member('editor');
    $q = Question::factory()->create(['text' => 'Za co jste letos nejvíc vděční?']);
    $q->forceFill(['moderation_status' => 'approved'])->save();
    $daily = app(DailyQuestions::class);
    $daily->plan($editor, $q, CarbonImmutable::parse('2026-12-01', 'Europe/Prague'), approve: true);

    expect($daily->publishDue())->toBeNull();
    $this->travelTo(CarbonImmutable::parse('2026-12-01 06:00', 'Europe/Prague'));
    $entry = $daily->publishDue();
    expect($entry)->not->toBeNull()->and($q->fresh()->isVisible())->toBeTrue();
    expect($daily->publishDue())->toBeNull(); // idempotent
    expect($daily->current()->question_id)->toBe($q->id);
    Notification::assertSentTo($editor, DailyQuestionReady::class);

    $this->get('/')->assertSee('Za co jste letos nejvíc vděční?')->assertSee('OTÁZKA DNE · 1. 12.');
    $this->get('/otazka-dne')->assertRedirect($q->url());
});

it('never publishes an unapproved draft automatically', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-02 09:00', 'Europe/Prague'));
    $q = Question::factory()->create();
    app(DailyQuestions::class)->plan(member('editor'), $q, CarbonImmutable::parse('2026-12-02', 'Europe/Prague'));
    expect(app(DailyQuestions::class)->publishDue())->toBeNull()->and($q->fresh()->isVisible())->toBeFalse();
});

it('refuses planning in the past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-02 09:00', 'Europe/Prague'));
    expect(fn () => app(DailyQuestions::class)->plan(member('editor'), Question::factory()->create(), CarbonImmutable::parse('2026-12-01', 'Europe/Prague')))->toThrow(QuestionException::class);
});

it('computes the time until the next question in Prague time', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 22:17:42', 'Europe/Prague'));
    expect(app(DailyQuestions::class)->nextPublishAt()->format('Y-m-d H:i'))->toBe('2026-12-02 06:00');
});

it('evaluates finished days with real numbers', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 07:00', 'Europe/Prague'));
    $q = liveQuestion();
    $entry = DailyQuestion::create(['date' => '2026-12-01', 'question_id' => $q->id, 'status' => 'published', 'published_at' => now()]);
    answerOf($q, null, 'Ano');
    $this->travelTo(CarbonImmutable::parse('2026-12-02 00:15', 'Europe/Prague'));
    expect(app(DailyQuestions::class)->evaluatePending())->toBe(1);
    expect($entry->fresh()->evaluation['answers'])->toBe(1);
});

it('runs the admin calendar', function () {
    $this->actingAs(member('editor'))->get('/admin/otazka-dne')->assertOk()->assertSee('volno');
});
