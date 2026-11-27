<?php

namespace App\Domain\AI\Services;

use App\Domain\Identity\Contracts\PersonalDataContributor;
use App\Models\AiGeneration;
use App\Models\AiGenerationCache;
use App\Models\AiUsageLog;
use App\Models\AnswerVariant;
use App\Models\User;

class AiPersonalData implements PersonalDataContributor
{
    public function key(): string
    {
        return 'ai_stylizace';
    }

    public function export(User $user): array
    {
        return [
            'pocet_generovani' => AiGeneration::where('user_id', $user->id)->count(),
            'navrzene_varianty' => AnswerVariant::where('user_id', $user->id)->latest('created_at')->limit(500)->get(['text', 'created_at', 'used_at'])->toArray(),
            'poznamka' => 'Poskytovateli AI se posílá jen text otázky, odpovědi a instrukce stylu – bez e-mailu a jména.',
        ];
    }

    public function erase(User $user, bool $deleteContent): void
    {
        AnswerVariant::where('user_id', $user->id)->delete();
        AiGeneration::where('user_id', $user->id)->update(['user_id' => null]);
        AiUsageLog::where('user_id', $user->id)->update(['user_id' => null]);
        AiGenerationCache::where('scope', 'user:'.$user->id)->delete();
    }
}
