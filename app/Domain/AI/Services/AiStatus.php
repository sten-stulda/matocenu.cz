<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Models\AiModel;
use App\Models\User;

class AiStatus
{
    public function __construct(private Settings $settings, private BudgetGuard $budget) {}

    /** @return list<AiModel> primary first, then fallback – only active models of active providers */
    public function models(): array
    {
        $models = AiModel::with('provider')->where('is_active', true)
            ->where(fn ($q) => $q->where('is_primary', true)->orWhere('is_fallback', true))
            ->whereHas('provider', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_primary')->get()->all();

        return array_values($models);
    }

    /**
     * Is AI stylisation offered to this viewer? The offline test provider is only offered
     * outside production or to AI administrators (it produces marked copies, not humour).
     */
    public function availableFor(?User $user): bool
    {
        if (! $this->settings->bool('ai.enabled')) {
            return false;
        }
        $models = $this->models();
        if (! $models) {
            return false;
        }
        if ($models[0]->provider->driver === 'fake') {
            return ! app()->isProduction() || ($user?->can('ai.manage') ?? false);
        }

        return true;
    }

    public function isTestMode(): bool
    {
        $m = $this->models();

        return $m && $m[0]->provider->driver === 'fake';
    }

    /** Reason shown when AI is not available (UX state "nedostupná AI" / "vyčerpaný limit"). */
    public function unavailableReason(): string
    {
        if (! $this->settings->bool('ai.enabled')) {
            return 'Stylizace je teď vypnutá. Zveřejni originál – i ten má cenu.';
        }
        if ($this->budget->dailyExceeded() || $this->budget->monthlyExceeded()) {
            return 'Aj AI si občas potřebuje oddechnout – rozpočet je pro dnešek vyčerpaný.';
        }

        return 'Aj AI si občas potřebuje oddechnout. Zveřejni originál, nebo to zkus za chvíli.';
    }
}
