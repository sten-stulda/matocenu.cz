<?php

namespace App\Domain\Questions\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Administration\Services\Settings;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Events\DailyQuestionPublished;
use App\Domain\Questions\Exceptions\QuestionException;
use App\Models\Answer;
use App\Models\DailyQuestion;
use App\Models\LanguageStyle;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Editorial calendar for the question of the day. Dates are calendar days in Europe/Prague;
 * publication happens at the configured local time via the scheduler.
 */
class DailyQuestions
{
    public function __construct(private Settings $settings, private AuditLogger $audit) {}

    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now(config('matocenu.display_timezone'));
    }

    public function publishTimeFor(CarbonImmutable $localDate): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $this->settings->string('questions.daily_publish_time') ?: '06:00') + [1 => 0]);

        return $localDate->setTimezone(config('matocenu.display_timezone'))->startOfDay()->setTime($h, $m);
    }

    /** The currently live question of the day (latest published entry). */
    public function current(): ?DailyQuestion
    {
        return DailyQuestion::query()->where('status', 'published')
            ->whereHas('question', fn ($q) => $q->visible())
            ->with(['question.category', 'style'])
            ->orderByDesc('date')->first();
    }

    /** When the next question of the day goes live (next publish time after now). */
    public function nextPublishAt(): CarbonImmutable
    {
        $now = $this->localNow();
        $todayAt = $this->publishTimeFor($now);

        return $now->lt($todayAt) ? $todayAt : $this->publishTimeFor($now->addDay());
    }

    public function plan(User $editor, Question $question, CarbonImmutable $date, bool $approve = false): DailyQuestion
    {
        if ($date->lt($this->localNow()->startOfDay())) {
            throw new QuestionException('Do minulosti plánovat nelze.');
        }
        $existing = DailyQuestion::whereDate('date', $date->toDateString())->first();
        if ($existing && $existing->status === 'published') {
            throw new QuestionException('Na tento den už otázka dne vyšla.');
        }
        $entry = DailyQuestion::updateOrCreate(['date' => $date->toDateString()], [
            'question_id' => $question->id,
            'language_style_id' => $this->scheduledStyleId($date),
            'status' => $approve ? 'approved' : 'planned',
            'planned_by' => $editor->id,
        ]);
        $question->forceFill(['is_daily_candidate' => true])->save();
        $this->audit->log('daily.planned', $entry, ['date' => $date->toDateString(), 'question_id' => $question->id, 'approved' => $approve]);

        return $entry;
    }

    /** Weekly "personality of the day" from settings (1 = Monday … 7 = Sunday). */
    public function scheduledStyleId(CarbonImmutable $date): ?int
    {
        $map = (array) $this->settings->get('daily.style_schedule');
        $slug = $map[(string) $date->dayOfWeekIso] ?? null;

        return $slug ? LanguageStyle::where('slug', $slug)->where('is_active', true)->value('id') : null;
    }

    public function approve(User $editor, DailyQuestion $entry): void
    {
        if ($entry->status !== 'planned') {
            throw new QuestionException('Schválit lze jen naplánovaný návrh.');
        }
        $entry->update(['status' => 'approved']);
        $this->audit->log('daily.approved', $entry, [], $editor->id);
    }

    public function unplan(User $editor, DailyQuestion $entry): void
    {
        if ($entry->status === 'published') {
            throw new QuestionException('Zveřejněnou otázku dne nelze vyřadit z kalendáře.');
        }
        $this->audit->log('daily.removed', $entry, ['date' => $entry->date->toDateString()], $editor->id);
        $entry->delete();
    }

    /**
     * Publish today's approved entry once its publish time has passed. Idempotent; run by the scheduler.
     * Only "approved" entries are published automatically – drafts never go live on their own.
     */
    public function publishDue(): ?DailyQuestion
    {
        $now = $this->localNow();
        if ($now->lt($this->publishTimeFor($now))) {
            return null;
        }

        return DB::transaction(function () use ($now) {
            $entry = DailyQuestion::whereDate('date', $now->toDateString())->where('status', 'approved')->lockForUpdate()->first();
            if (! $entry) {
                return null;
            }
            $q = Question::lockForUpdate()->findOrFail($entry->question_id);
            $q->moderation_status = ModerationStatus::Approved;
            $q->publication_status = PublicationStatus::Published;
            $q->published_at ??= now();
            $q->last_activity_at = now();
            $q->save();
            $entry->update(['status' => 'published', 'published_at' => now()]);
            DB::afterCommit(fn () => event(new DailyQuestionPublished($entry)));

            return $entry;
        });
    }

    /** Daily evaluation of a finished question of the day (real numbers only). */
    public function evaluate(DailyQuestion $entry): array
    {
        $q = $entry->question;
        $visible = ModerationStatus::publicValues();
        $answers = Answer::where('question_id', $q->id)->whereIn('moderation_status', $visible);
        $top = (clone $answers)->orderByDesc('score')->orderBy('published_at')->first();
        $data = [
            'answers' => (clone $answers)->count(),
            'stylized_share' => (clone $answers)->count() ? round((clone $answers)->where('is_stylized', true)->count() / (clone $answers)->count() * 100) : 0,
            'reactions' => (int) DB::table('reactions')->where('reactable_type', 'answer')->whereIn('reactable_id', (clone $answers)->select('id'))->count(),
            'comments' => $q->comments_count,
            'top_answer_id' => $top?->id,
        ];
        $entry->update(['evaluation' => $data, 'evaluated_at' => now()]);

        return $data;
    }

    /** Evaluate all published entries older than today that were not evaluated yet. */
    public function evaluatePending(): int
    {
        $n = 0;
        DailyQuestion::with('question')->where('status', 'published')->whereNull('evaluated_at')
            ->whereDate('date', '<', $this->localNow()->toDateString())->each(function (DailyQuestion $e) use (&$n) {
                $this->evaluate($e);
                $n++;
            });

        return $n;
    }
}
