<?php

namespace App\Domain\Questions\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Answer;
use App\Models\DailyQuestion;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Collects everything the public homepage shows. Only real data – sections without
 * content render natural empty states (never invented activity).
 */
class HomepageData
{
    public function __construct(private DailyQuestions $daily) {}

    /** @return array<string, mixed> */
    public function build(?User $viewer): array
    {
        $entry = $this->daily->current();
        $question = $entry?->question;

        $shared = Cache::remember('mtc:home:shared', 60, fn () => [
            'popular' => $this->popular(),
            'discussed' => $this->discussed(),
            'latest' => Question::query()->visible()->with('category')->latest('published_at')->limit(5)->get(),
            'unanswered' => Question::query()->live()->where('answers_count', 0)->latest('published_at')->limit(3)->get(),
            'bestWeek' => $this->bestAnswers(now()->subDays(7), 3),
            'quoteOfDay' => $this->bestAnswers(now()->subDay(), 1)->first(),
            'archive' => DailyQuestion::with('question')->where('status', 'published')->orderByDesc('date')->skip(1)->limit(5)->get(),
        ]);

        return $shared + [
            'daily' => $entry,
            'dailyQuestion' => $question,
            'nextDailyAt' => $this->daily->nextPublishAt(),
            'dailyTop' => $question ? $this->answersFor($question, 'top', 3) : collect(),
            'dailyNewest' => $question ? $this->answersFor($question, 'new', 3) : collect(),
            'random' => Question::query()->visible()->inRandomOrder()->first(),
            'local' => $this->local($viewer),
            'viewerRegion' => $viewer?->location?->region(),
            'sections' => true,
        ];
    }

    /** @return Collection<int, Answer> */
    public function answersFor(Question $q, string $sort, int $limit): Collection
    {
        return Answer::query()->with(['user', 'question'])
            ->where('question_id', $q->id)->publiclyVisible()
            ->when($sort === 'top', fn ($w) => $w->where('score', '>', 0)->orderByDesc('score'))
            ->orderByDesc('published_at')->limit($limit)->get();
    }

    /** @return Collection<int, Answer> */
    public function bestAnswers(\DateTimeInterface $since, int $limit): Collection
    {
        return Answer::query()->with(['user', 'question'])->publiclyVisible()
            ->where('published_at', '>=', $since)->where('score', '>', 0)
            ->whereHas('question', fn ($q) => $q->visible())
            ->orderByDesc('score')->orderByDesc('unique_reactors_count')->limit($limit)->get();
    }

    /** @return Collection<int, Question> */
    private function popular(): Collection
    {
        return Question::query()->visible()->where('answers_count', '>', 0)
            ->where('published_at', '>=', now()->subDays(30))
            ->orderByDesc('answers_count')->limit(4)->get();
    }

    /** @return Collection<int, Question> */
    private function discussed(): Collection
    {
        return Question::query()->visible()->where('last_activity_at', '>=', now()->subDays(3))
            ->where(fn ($q) => $q->where('comments_count', '>', 0)->orWhere('answers_count', '>', 0))
            ->orderByDesc('last_activity_at')->limit(4)->get();
    }

    /** @return Collection<int, Question>|null null = viewer has no region set */
    private function local(?User $viewer): ?Collection
    {
        $region = $viewer?->location?->region();
        if (! $region) {
            return null;
        }

        return Question::query()->visible()->whereIn('location_id', $region->descendantIds())
            ->latest('published_at')->limit(3)->get();
    }

    public static function visibleStatuses(): array
    {
        return ModerationStatus::publicValues();
    }
}
