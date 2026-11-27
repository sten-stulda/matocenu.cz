<?php

namespace App\Domain\Rankings\Services;

use App\Domain\Administration\Services\Settings;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\Location;
use App\Models\RankingSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transparent, configurable rankings – never just absolute reaction counts.
 *
 * Answer rank score (shown on /zebricky/jak-pocitame):
 *   rank = trusted_score × (1 + u · ln(1 + unique_reactors)) ÷ (1 + age_days)^(g / 4)
 *   trusted_score = Σ reaction weight × reactor trust (see AnswerScore), u = rankings.unique_weight,
 *   g = rankings.gravity. Only the author's best N answers per day count (rankings.max_answers_per_day).
 *   Answers whose reactions come mostly (> 50 %) from accounts younger than 3 days are flagged and halved.
 */
class RankingEngine
{
    public function __construct(private Settings $settings) {}

    /** @return list<array<string, mixed>> */
    public function answers(CarbonImmutable $from, CarbonImmutable $to, int $limit = 50): array
    {
        $rows = DB::select(<<<'SQL'
            WITH base AS (
                SELECT a.id, a.user_id, a.is_anonymous, a.score::float AS trusted, a.unique_reactors_count AS uniq,
                       (a.published_at AT TIME ZONE ?)::date AS day,
                       GREATEST(EXTRACT(EPOCH FROM (now() - a.published_at)) / 86400.0, 0) AS age_days,
                       (SELECT count(*) FROM reactions r JOIN users ru ON ru.id = r.user_id
                          WHERE r.reactable_type = 'answer' AND r.reactable_id = a.id AND ru.created_at > now() - interval '3 days') AS young
                FROM answers a
                JOIN questions q ON q.id = a.question_id AND q.deleted_at IS NULL AND q.moderation_status = ANY(?::text[])
                JOIN users u ON u.id = a.user_id AND u.status = 'active'
                WHERE a.deleted_at IS NULL AND a.moderation_status = ANY(?::text[])
                  AND a.published_at BETWEEN ? AND ? AND a.score > 0
            ), scored AS (
                SELECT *, trusted * (1 + ? * ln(1 + uniq)) / power(1 + age_days, ? / 4.0)
                          * CASE WHEN uniq > 0 AND young::float / uniq > 0.5 THEN 0.5 ELSE 1 END AS rank_score,
                       (uniq > 0 AND young::float / uniq > 0.5) AS suspicious,
                       row_number() OVER (PARTITION BY user_id, day ORDER BY trusted DESC) AS nth
                FROM base
            )
            SELECT id, user_id, is_anonymous, round(rank_score::numeric, 4) AS score, uniq, suspicious
            FROM scored WHERE nth <= ? ORDER BY rank_score DESC, uniq DESC, id LIMIT ?
        SQL, [
            config('matocenu.display_timezone'), $this->pg(ModerationStatus::publicValues()), $this->pg(ModerationStatus::publicValues()),
            $from->utc(), $to->utc(),
            (float) $this->settings->get('rankings.unique_weight'), (float) $this->settings->get('rankings.gravity'),
            max(1, $this->settings->int('rankings.max_answers_per_day')), $limit,
        ]);

        return $this->ranked(array_map(fn ($r) => ['id' => $r->id, 'user_id' => $r->user_id, 'anonymous' => (bool) $r->is_anonymous, 'score' => (float) $r->score, 'unique' => (int) $r->uniq, 'suspicious' => (bool) $r->suspicious], $rows));
    }

    /** Authors by the summed rank of their (non-anonymous, capped) answers. @return list<array<string, mixed>> */
    public function authors(CarbonImmutable $from, CarbonImmutable $to, int $limit = 20): array
    {
        $byUser = [];
        foreach ($this->answers($from, $to, 1000) as $a) {
            if ($a['anonymous']) {
                continue;
            }
            $byUser[$a['user_id']]['score'] = ($byUser[$a['user_id']]['score'] ?? 0) + $a['score'];
            $byUser[$a['user_id']]['answers'] = ($byUser[$a['user_id']]['answers'] ?? 0) + 1;
        }
        arsort($byUser);
        uasort($byUser, fn ($x, $y) => $y['score'] <=> $x['score']);
        $items = [];
        foreach (array_slice($byUser, 0, $limit, true) as $uid => $d) {
            $top = DB::table('reactions')->join('answers', 'answers.id', '=', 'reactions.reactable_id')->join('reaction_types', 'reaction_types.id', '=', 'reactions.reaction_type_id')
                ->where('reactions.reactable_type', 'answer')->where('answers.user_id', $uid)->whereBetween('reactions.created_at', [$from->utc(), $to->utc()])
                ->groupBy('reaction_types.label')->orderByRaw('count(*) desc')->value('reaction_types.label');
            $items[] = ['id' => $uid, 'score' => round($d['score'], 2), 'answers' => $d['answers'], 'top_reaction' => $top];
        }

        return $this->ranked($items);
    }

    /** Most interesting = activity in the period (answers + comments + unique reactors). @return list<array<string, mixed>> */
    public function questions(CarbonImmutable $from, CarbonImmutable $to, string $mode = 'interesting', int $limit = 10): array
    {
        $visible = $this->pg(ModerationStatus::publicValues());
        $rows = DB::select(<<<'SQL'
            SELECT q.id,
                (SELECT count(*) FROM answers a WHERE a.question_id = q.id AND a.deleted_at IS NULL AND a.moderation_status = ANY(?::text[]) AND a.published_at BETWEEN ? AND ?) AS answers,
                (SELECT count(*) FROM comments c WHERE c.commentable_type = 'question' AND c.commentable_id = q.id AND c.deleted_at IS NULL AND c.created_at BETWEEN ? AND ?) AS comments,
                (SELECT count(DISTINCT r.user_id) FROM reactions r JOIN answers a ON a.id = r.reactable_id AND r.reactable_type = 'answer' WHERE a.question_id = q.id AND r.created_at BETWEEN ? AND ?) AS reactors
            FROM questions q
            WHERE q.deleted_at IS NULL AND q.moderation_status = ANY(?::text[]) AND q.publication_status IN ('published','closed','archived') AND q.published_at <= now()
        SQL, [$visible, $from->utc(), $to->utc(), $from->utc(), $to->utc(), $from->utc(), $to->utc(), $visible]);
        $items = [];
        foreach ($rows as $r) {
            $score = $mode === 'popular' ? (int) $r->answers : (int) $r->answers + 2 * (int) $r->comments + (int) $r->reactors;
            if ($score > 0) {
                $items[] = ['id' => $r->id, 'score' => $score, 'answers' => (int) $r->answers, 'comments' => (int) $r->comments, 'reactors' => (int) $r->reactors];
            }
        }
        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $this->ranked(array_slice($items, 0, $limit));
    }

    /** Regions by public, non-anonymous activity of members who show their region. @return list<array<string, mixed>> */
    public function regions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = [];
        foreach (Location::where('type', 'region')->get() as $region) {
            $userIds = DB::table('users')->join('user_settings', 'user_settings.user_id', '=', 'users.id')
                ->whereIn('users.location_id', $region->descendantIds())->where('user_settings.show_region', true)->where('users.status', 'active')->select('users.id');
            $answers = DB::table('answers')->whereIn('user_id', $userIds)->where('is_anonymous', false)->whereNull('deleted_at')
                ->whereIn('moderation_status', ModerationStatus::publicValues())->whereBetween('published_at', [$from->utc(), $to->utc()]);
            $n = (clone $answers)->count();
            if ($n > 0) {
                $items[] = ['id' => $region->id, 'name' => $region->name, 'score' => $n + (int) (clone $answers)->sum('reactions_count'), 'answers' => $n];
            }
        }
        usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $this->ranked($items);
    }

    /** Compute and store all snapshots for the current period (scheduler, hourly). */
    public function snapshot(string $period, ?CarbonImmutable $at = null): void
    {
        [$from, $to, $key] = Periods::current($period, $at);
        foreach (['answers' => fn () => $this->answers($from, $to), 'authors' => fn () => $this->authors($from, $to), 'questions_interesting' => fn () => $this->questions($from, $to), 'questions_popular' => fn () => $this->questions($from, $to, 'popular'), 'regions' => fn () => $this->regions($from, $to)] as $type => $fn) {
            RankingSnapshot::updateOrCreate(['type' => $type, 'period' => $period, 'period_key' => $key, 'location_id' => null], ['items' => $fn(), 'computed_at' => now()]);
        }
    }

    /** Latest snapshot for the current period (computed on demand if missing). @return list<array<string, mixed>> */
    public function get(string $type, string $period): array
    {
        [, , $key] = Periods::current($period);
        $snap = RankingSnapshot::where(['type' => $type, 'period' => $period, 'period_key' => $key])->whereNull('location_id')->first();
        if (! $snap || $snap->computed_at->lt(now()->subMinutes(70))) {
            $this->snapshot($period);
            $snap = RankingSnapshot::where(['type' => $type, 'period' => $period, 'period_key' => $key])->whereNull('location_id')->first();
        }

        return $snap !== null ? $snap->items : [];
    }

    /** Rank change vs. the previous period's snapshot (positive = moved up). @return array<int, int|null> */
    public function deltas(string $type, string $period, array $items): array
    {
        [, , $prevKey] = Periods::previous($period);
        $prev = collect(RankingSnapshot::where(['type' => $type, 'period' => $period, 'period_key' => $prevKey])->whereNull('location_id')->value('items') ?? [])->keyBy('id');
        $out = [];
        foreach ($items as $i) {
            $out[$i['id']] = isset($prev[$i['id']]) ? (int) $prev[$i['id']]['rank'] - (int) $i['rank'] : null;
        }

        return $out;
    }

    /** @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>> */
    private function ranked(array $items): array
    {
        foreach ($items as $i => &$item) {
            $item['rank'] = $i + 1;
        }

        return $items;
    }

    /** @param list<string> $values */
    private function pg(array $values): string
    {
        return '{'.implode(',', $values).'}';
    }
}
