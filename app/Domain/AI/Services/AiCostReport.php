<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Models\AiGeneration;
use App\Models\AiUsageLog;

class AiCostReport
{
    public function __construct(private BudgetGuard $budget, private Settings $settings) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $since = now()->subDays(30);
        $tz = config('matocenu.display_timezone');
        $gens = AiGeneration::where('created_at', '>=', $since)->where('operation', '!=', 'prompt_test');
        $total = (clone $gens)->count();
        $succeeded = (clone $gens)->where('status', 'succeeded')->count();
        $paid = (clone $gens)->where('status', 'succeeded')->where('cache_hit', false)->count();
        $hits = AiUsageLog::where('created_at', '>=', $since)->where('status', 'cache_hit')->count();
        $calls = AiUsageLog::where('created_at', '>=', $since)->where('status', 'ok')->count();
        $todayCost = (float) $this->budget->spentToday();
        $todayGens = AiGeneration::where('created_at', '>=', now($tz)->startOfDay())->where('cache_hit', false)->where('status', 'succeeded')->count();

        $days = collect(range(6, 0))->map(function ($i) use ($tz) {
            $d = now($tz)->subDays($i)->startOfDay();

            return ['label' => $d->translatedFormat('D'), 'czk' => (float) AiUsageLog::whereBetween('created_at', [$d, $d->copy()->endOfDay()])->sum('cost_czk'), 'today' => $i === 0];
        })->all();

        return [
            'today' => $todayCost,
            'month' => (float) $this->budget->spentThisMonth(),
            'daily_budget' => (float) $this->settings->decimal('ai.daily_budget_czk'),
            'monthly_budget' => (float) $this->settings->decimal('ai.monthly_budget_czk'),
            'per_generation' => $todayGens ? $todayCost / $todayGens : null,
            'success_rate' => $total ? $succeeded / $total * 100 : null,
            'rejected' => AiUsageLog::where('created_at', '>=', $since)->where('status', 'rejected')->count(),
            'avg_latency' => ($v = AiUsageLog::where('created_at', '>=', $since)->where('status', 'ok')->avg('duration_ms')) ? (int) $v : null,
            'saved' => (float) AiUsageLog::where('created_at', '>=', $since)->sum('saved_czk'),
            'cache_hits' => $hits,
            'hit_rate' => ($hits + $paid) ? round($hits / ($hits + $paid) * 100) : null,
            'calls' => $calls,
            'days' => $days,
            'by_model' => AiUsageLog::where('created_at', '>=', $since)->whereNotNull('model')->groupBy('model')
                ->selectRaw('model, count(*) as calls, sum(input_tokens) as input, sum(output_tokens + extra_tokens) as output, sum(cost_czk) as czk')->orderByDesc('czk')->toBase()->get(),
            'by_operation' => AiUsageLog::where('created_at', '>=', $since)->groupBy('operation')
                ->selectRaw("operation, count(*) filter (where status in ('ok','error')) as calls, count(*) filter (where status = 'error') as errors, sum(cost_czk) as czk")->orderByDesc('czk')->toBase()->get(),
        ];
    }
}
