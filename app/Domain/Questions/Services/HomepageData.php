<?php

namespace App\Domain\Questions\Services;

use App\Models\User;

/**
 * Collects everything the public homepage shows. Only real data – sections without
 * content render natural empty states (never invented activity).
 */
class HomepageData
{
    /** @return array<string, mixed> */
    public function build(?User $viewer): array
    {
        return [
            'daily' => null,
            'nextDailyAt' => null,
        ];
    }
}
