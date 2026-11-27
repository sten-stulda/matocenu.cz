<?php

namespace App\Domain\Administration\Services;

/**
 * Counters shown next to admin menu items (moderation queue, pending prompts…).
 * Modules register closures in config('matocenu.admin_badges').
 */
class AdminBadges
{
    /** @return array<string, int> */
    public function all(): array
    {
        $out = [];
        foreach (config('matocenu.admin_badges', []) as $route => $class) {
            $out[$route] = (int) app($class)();
        }

        return $out;
    }
}
