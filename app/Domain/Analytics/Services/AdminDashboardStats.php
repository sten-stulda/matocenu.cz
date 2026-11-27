<?php

namespace App\Domain\Analytics\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real numbers for the admin dashboard (no estimates). Cached briefly.
 */
class AdminDashboardStats
{
    /** @return array<string, mixed> */
    public function users(): array
    {
        return Cache::remember('mtc:admin:users', 60, function () {
            $today = now()->startOfDay();

            return [
                'total' => User::where('status', '!=', 'deleted')->count(),
                'new_today' => User::where('created_at', '>=', $today)->count(),
                'new_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'active_24h' => User::where('last_seen_at', '>=', now()->subDay())->count(),
                'active_7d' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'retention_7d' => $this->retention(),
            ];
        });
    }

    /**
     * 7-day retention: of users registered 8–14 days ago, the share seen in the last 7 days.
     */
    public function retention(): ?float
    {
        $cohort = User::whereBetween('created_at', [now()->subDays(14), now()->subDays(8)]);
        $size = (clone $cohort)->count();
        if ($size === 0) {
            return null;
        }
        $returned = (clone $cohort)->where('last_seen_at', '>=', now()->subDays(7))->count();

        return round($returned / $size * 100, 1);
    }

    /** @return list<array{name: string, last_run: ?string}> */
    public function scheduledTasks(): array
    {
        return collect(config('matocenu.scheduled_tasks', []))->map(fn ($label, $key) => [
            'name' => $label,
            'last_run' => Cache::get('mtc:task:'.$key),
        ])->values()->all();
    }

    public function failedJobs(): int
    {
        return DB::table('failed_jobs')->count();
    }
}
