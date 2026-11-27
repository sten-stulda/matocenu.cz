<?php

namespace App\Domain\Moderation\Services;

use App\Models\ModerationAppeal;

class AppealCount
{
    public function __invoke(): int
    {
        return ModerationAppeal::where('status', 'open')->count();
    }
}
