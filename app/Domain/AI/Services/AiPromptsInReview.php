<?php

namespace App\Domain\AI\Services;

use App\Models\AiPromptVersion;

class AiPromptsInReview
{
    public function __invoke(): int
    {
        return AiPromptVersion::where('status', 'review')->count();
    }
}
