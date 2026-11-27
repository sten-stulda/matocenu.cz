<?php

namespace App\Domain\Questions\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Answer;
use App\Models\DailyQuestion;
use App\Models\Question;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
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

        // Only IDs go to the cache: Laravel 13 refuses to unserialize objects (cache.serializable_classes = false).
        $ids = Cache::remember('mtc:home:shared:ids', 60, fn () => [
            'popular' => $this->popular()->pluck('id')->all(),
            'discussed' => $this->discussed()->pluck('id')->all(),
            'latest' => Question::query()->visible()->latest('published_at')->limit(5)->pluck('id')->all(),
            'unanswered' => Question::query()->live()->where('answers_count', 0)->latest('published_at')->limit(3)->pluck('id')->all(),
            'bestWeek' => $this->bestAnswers(now()->subDays(7), 3)->pluck('id')->all(),
            'quoteOfDay' => $this->bestAnswers(now()->subDay(), 1)->pluck('id')->all(),
            'quote' => Quote::where('period', 'day')->whereHas('answer', fn ($q) => $q->publiclyVisible())->latest('awarded_at')->limit(1)->pluck('id')->all(),
            'archive' => DailyQuestion::where('status', 'published')->orderByDesc('date')->skip(1)->limit(5)->pluck('id')->all(),
        ]);
        $answers = fn () => Answer::query()->with(['user', 'question', 'style'])->publiclyVisible();
        $shared = [
            'popular' => $this->ordered(Question::query()->visible(), $ids['popular']),
            'discussed' => $this->ordered(Question::query()->visible(), $ids['discussed']),
            'latest' => $this->ordered(Question::query()->visible()->with('category'), $ids['latest']),
            'unanswered' => $this->ordered(Question::query()->live(), $ids['unanswered']),
            'bestWeek' => $this->ordered($answers(), $ids['bestWeek']),
            'quoteOfDay' => $this->ordered($answers(), $ids['quoteOfDay'])->first(),
            'quote' => $this->ordered(Quote::with(['answer.user', 'answer.style']), $ids['quote'])->first(),
            'archive' => $this->ordered(DailyQuestion::with('question'), $ids['archive']),
        ];

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
        return Answer::query()->with(['user', 'question', 'style'])
            ->where('question_id', $q->id)->publiclyVisible()
            ->when($sort === 'top', fn ($w) => $w->where('score', '>', 0)->orderByDesc('score'))
            ->orderByDesc('published_at')->limit($limit)->get();
    }

    /** @return Collection<int, Answer> */
    public function bestAnswers(\DateTimeInterface $since, int $limit): Collection
    {
        return Answer::query()->with(['user', 'question', 'style'])->publiclyVisible()
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

    /**
     * Reload cached IDs in their original order (still applying the query's visibility scopes).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<int>  $ids
     * @return EloquentCollection<int, TModel>
     */
    private function ordered(Builder $query, array $ids): EloquentCollection
    {
        if ($ids === []) {
            return $query->getModel()->newCollection();
        }
        $found = $query->whereKey($ids)->get()->keyBy(fn (Model $m) => $m->getKey());

        return $query->getModel()->newCollection(array_values(array_filter(array_map(fn ($id) => $found->get($id), $ids))));
    }

    public static function visibleStatuses(): array
    {
        return ModerationStatus::publicValues();
    }
}
