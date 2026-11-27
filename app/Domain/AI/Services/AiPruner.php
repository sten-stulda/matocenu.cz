<?php

namespace App\Domain\AI\Services;

use App\Domain\Administration\Services\Settings;
use App\Models\AiGeneration;
use App\Models\AiGenerationCache;
use App\Models\AiUsageLog;
use App\Models\AnswerVariant;

class AiPruner
{
    public function __invoke(): string
    {
        $days = app(Settings::class)->int('privacy.retention_days_ai_logs');
        $logs = AiUsageLog::where('created_at', '<', now()->subDays($days))->delete();
        $gens = AiGeneration::where('created_at', '<', now()->subDays($days))->delete();
        $cache = AiGenerationCache::where('expires_at', '<', now())->delete();
        // Unpublished drafts' variants are only useful for a day.
        $variants = AnswerVariant::whereNull('used_at')->where('created_at', '<', now()->subDays(2))->delete();

        return "AI: {$logs} logů, {$gens} generování, {$cache} cache, {$variants} nepoužitých variant smazáno.";
    }
}
