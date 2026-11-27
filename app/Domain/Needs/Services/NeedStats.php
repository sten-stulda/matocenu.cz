<?php

namespace App\Domain\Needs\Services;

use App\Domain\Needs\Enums\Frequency;
use App\Domain\Needs\Enums\InterestKind;
use App\Models\Location;
use App\Models\Need;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Anonymised aggregates of real interest. k-anonymity: amounts only from ≥ 5 payers,
 * regions only with ≥ 3 people. Declared willingness to pay is never presented as revenue.
 */
class NeedStats
{
    public const K_AMOUNT = 5;

    public const K_REGION = 3;

    /** @return array<string, mixed> */
    public function forNeed(Need $need): array
    {
        $payers = DB::table('need_interests')->where('need_id', $need->id)->where('kind', InterestKind::WouldPay->value);
        $amounts = (clone $payers)->whereNotNull('amount')->where('currency', 'CZK')->orderBy('amount')->pluck('amount')->map(fn ($a) => (float) $a)->values();
        $freq = (clone $payers)->whereNotNull('frequency')->groupBy('frequency')->orderByRaw('count(*) desc')->value('frequency');
        $frequency = $freq ? Frequency::from($freq) : null;
        $showAmounts = $amounts->count() >= self::K_AMOUNT;
        $median = $showAmounts ? $this->percentile($amounts, 0.5) : null;

        return [
            'payers' => (clone $payers)->count(),
            'commitments' => DB::table('need_interests')->where('need_id', $need->id)->where('is_commitment', true)->distinct()->count('user_id'),
            'amounts_shown' => $showAmounts,
            'amount_samples' => $amounts->count(),
            'p25' => $showAmounts ? $this->percentile($amounts, 0.25) : null,
            'median' => $median,
            'p75' => $showAmounts ? $this->percentile($amounts, 0.75) : null,
            'frequency' => $frequency,
            'monthly_estimate' => $showAmounts && $frequency ? $this->monthly($amounts->count(), $median, $frequency) : null,
            'regions' => $this->regions(DB::table('need_interests')->where('need_id', $need->id)),
            'growth' => $this->growth($need->id),
        ];
    }

    /** "Poptávka podle kraje": unique interested people per region across all needs. @return list<array{name: string, count: int}> */
    public function regionDemand(?int $categoryId = null): array
    {
        $q = DB::table('need_interests')->join('needs', 'needs.id', '=', 'need_interests.need_id')->whereNull('needs.deleted_at')
            ->whereIn('needs.moderation_status', ['approved', 'reported'])
            ->when($categoryId, fn ($w) => $w->where('needs.category_id', $categoryId));

        return $this->regions($q);
    }

    /** @return list<array{name: string, count: int}> */
    private function regions($query): array
    {
        $byLocation = (clone $query)->whereNotNull('need_interests.location_id')->groupBy('need_interests.location_id')
            ->selectRaw('need_interests.location_id as loc, count(distinct need_interests.user_id) as n')->pluck('n', 'loc');
        $regions = [];
        foreach ($byLocation as $loc => $n) {
            $region = Location::find($loc)?->region();
            if ($region) {
                $regions[$region->name] = ($regions[$region->name] ?? 0) + (int) $n;
            }
        }
        arsort($regions);
        $out = [];
        foreach ($regions as $name => $n) {
            if ($n >= self::K_REGION) {
                $out[] = ['name' => $name, 'count' => $n];
            }
        }

        return array_slice($out, 0, 8);
    }

    /** Interest in the last 14 days vs. the 14 days before. @return array{recent: int, previous: int} */
    public function growth(int $needId): array
    {
        $base = DB::table('need_interests')->where('need_id', $needId);

        return [
            'recent' => (clone $base)->where('created_at', '>=', now()->subDays(14))->distinct()->count('user_id'),
            'previous' => (clone $base)->whereBetween('created_at', [now()->subDays(28), now()->subDays(14)])->distinct()->count('user_id'),
        ];
    }

    /** Non-binding monthly estimate only for recurring frequencies. */
    private function monthly(int $payers, float $median, Frequency $f): ?float
    {
        $factor = match ($f) {
            Frequency::Monthly => 1.0,
            Frequency::Weekly => 52 / 12,
            Frequency::Yearly => 1 / 12,
            default => null,
        };

        return $factor === null ? null : round($payers * $median * $factor);
    }

    /** @param Collection<int, float> $sorted */
    private function percentile(Collection $sorted, float $p): float
    {
        $n = $sorted->count();
        $idx = ($n - 1) * $p;
        $lo = (int) floor($idx);
        $hi = (int) ceil($idx);

        return round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * ($idx - $lo));
    }

    /** Top needs by unique interest (homepage "Co lidem chybí"). @return Collection<int, Need> */
    public function top(int $limit = 5): Collection
    {
        return Need::query()->visible()->whereIn('status', ['open', 'in_progress'])->where('interested_count', '>', 0)
            ->orderByDesc('interested_count')->limit($limit)->get();
    }
}
