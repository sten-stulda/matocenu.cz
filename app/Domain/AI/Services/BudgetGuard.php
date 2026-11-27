<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Domain\AI\Exceptions\AiBudgetExceeded;
use App\Models\AiGeneration;
use App\Models\AiUsageLog;
use App\Models\User;

/** Daily / monthly CZK budget and per-user quota. When exhausted, generation pauses automatically. */
class BudgetGuard
{
    public function __construct(private Settings $settings) {}

    public function spentToday(): string
    {
        return (string) (AiUsageLog::where('created_at', '>=', now(config('matocenu.display_timezone'))->startOfDay())->sum('cost_czk') ?: '0');
    }

    public function spentThisMonth(): string
    {
        return (string) (AiUsageLog::where('created_at', '>=', now(config('matocenu.display_timezone'))->startOfMonth())->sum('cost_czk') ?: '0');
    }

    public function userGenerationsToday(User $user): int
    {
        return AiGeneration::where('user_id', $user->id)->where('cache_hit', false)
            ->where('created_at', '>=', now(config('matocenu.display_timezone'))->startOfDay())->count();
    }

    public function dailyExceeded(): bool
    {
        return bccomp($this->spentToday(), $this->settings->decimal('ai.daily_budget_czk'), 4) >= 0;
    }

    public function monthlyExceeded(): bool
    {
        return bccomp($this->spentThisMonth(), $this->settings->decimal('ai.monthly_budget_czk'), 4) >= 0;
    }

    /** @throws AiBudgetExceeded */
    public function assertCanSpend(?User $user): void
    {
        if ($this->dailyExceeded() || $this->monthlyExceeded()) {
            throw new AiBudgetExceeded('Rozpočet na AI je pro dnešek vyčerpaný. Zveřejni originál – zítra to zase půjde.');
        }
        if ($user && ! $user->can('ai.manage') && $this->userGenerationsToday($user) >= $this->settings->int('ai.user_daily_generations')) {
            throw new AiBudgetExceeded('Dnešní limit stylizací máš vyčerpaný. Originál ale můžeš zveřejnit kdykoliv.');
        }
    }
}
