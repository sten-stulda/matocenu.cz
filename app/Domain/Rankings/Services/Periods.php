<?php

namespace App\Domain\Rankings\Services;

use Carbon\CarbonImmutable;

/** Calendar periods in Europe/Prague: day 2026-11-27, ISO week 2026-W48, month 2026-11. */
final class Periods
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} start, end, key */
    public static function current(string $period, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now(config('matocenu.display_timezone'));
        $at = $at->setTimezone(config('matocenu.display_timezone'));

        return match ($period) {
            'day' => [$at->startOfDay(), $at->endOfDay(), $at->format('Y-m-d')],
            'month' => [$at->startOfMonth(), $at->endOfMonth(), $at->format('Y-m')],
            default => [$at->startOfWeek(), $at->endOfWeek(), $at->format('o-\WW')],
        };
    }

    /** The period that just finished (yesterday, last week, last month). */
    public static function previous(string $period, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now(config('matocenu.display_timezone'));

        return self::current($period, match ($period) {
            'day' => $at->subDay(),
            'month' => $at->startOfMonth()->subDay(),
            default => $at->startOfWeek()->subDay(),
        });
    }

    public static function label(string $period): string
    {
        return ['day' => 'Den', 'week' => 'Týden', 'month' => 'Měsíc'][$period] ?? 'Týden';
    }
}
