<?php

namespace App\Domain\Quotes\Services;

use App\Domain\Gamification\Services\Achievements;
use App\Domain\Rankings\Services\Periods;
use App\Domain\Rankings\Services\RankingEngine;
use App\Models\Answer;
use App\Models\Quote;
use App\Notifications\QuoteAwarded;
use Carbon\CarbonImmutable;

/**
 * After a day/week/month ends, the best-ranked real answer becomes the quote of that period.
 * Legal, licence and commercial statuses start as unchecked / none / not approved.
 */
class QuoteSelector
{
    public function __construct(private RankingEngine $rankings, private Achievements $achievements) {}

    public function award(string $period, ?CarbonImmutable $at = null): ?Quote
    {
        [$from, $to, $key] = Periods::previous($period, $at);
        if (Quote::where('period', $period)->where('period_key', $key)->exists()) {
            return null;
        }
        $top = collect($this->rankings->answers($from, $to, 10))->first(fn ($i) => ! $i['suspicious']);
        if (! $top) {
            return null; // no real activity – no invented winner
        }
        $a = Answer::with(['question', 'style', 'user'])->find($top['id']);
        if (! $a) {
            return null;
        }
        $quote = Quote::create([
            'answer_id' => $a->id, 'period' => $period, 'period_key' => $key,
            'original_text' => $a->original_text, 'display_text' => $a->display_text, 'is_stylized' => $a->is_stylized,
            'language_style_id' => $a->language_style_id, 'author_id' => $a->user_id, 'source' => 'answer',
            'score' => $top['score'], 'reactions_count' => $a->reactions_count, 'unique_reactors_count' => $a->unique_reactors_count,
            'history' => [
                'question' => $a->question->text,
                'answered_at' => $a->published_at?->toIso8601String(),
                'style' => $a->style?->name,
                'ai_stylized' => $a->is_stylized,
                'rank_score' => $top['score'],
                'unique_reactors' => $a->unique_reactors_count,
            ],
            'awarded_at' => now(),
        ]);
        if ($a->user->isActive()) {
            $a->user->notify(new QuoteAwarded($quote));
            $this->achievements->evaluate($a->user);
        }

        return $quote;
    }

    /** Current quote of each period (most recent awarded). @return array<string, Quote|null> */
    public function current(): array
    {
        $out = [];
        foreach (array_keys(Quote::PERIODS) as $p) {
            $out[$p] = Quote::with(['answer.question', 'answer.user', 'style'])->where('period', $p)
                ->whereHas('answer', fn ($q) => $q->publiclyVisible())->latest('awarded_at')->first();
        }

        return $out;
    }
}
