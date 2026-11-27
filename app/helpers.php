<?php

use App\Domain\Administration\Services\FeatureFlags;
use App\Domain\Administration\Services\Settings;
use Carbon\CarbonInterface;

if (! function_exists('setting')) {
    function setting(string $key, mixed $fallback = null): mixed
    {
        return app(Settings::class)->get($key, $fallback);
    }
}

if (! function_exists('feature')) {
    function feature(string $key): bool
    {
        return app(FeatureFlags::class)->enabled($key);
    }
}

if (! function_exists('local_time')) {
    /** Date in the display timezone (Europe/Prague). */
    function local_time(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->copy()->setTimezone(config('matocenu.display_timezone'));
    }
}

if (! function_exists('cz_number')) {
    function cz_number(int|float|string|null $n, int $decimals = 0): string
    {
        return number_format((float) $n, $decimals, ',', "\u{00A0}");
    }
}

if (! function_exists('cz_plural')) {
    /** cz_plural(5, 'odpověď', 'odpovědi', 'odpovědí') → "5 odpovědí" */
    function cz_plural(int $n, string $one, string $few, string $many, bool $withNumber = true): string
    {
        $word = $n === 1 ? $one : (($n >= 2 && $n <= 4) ? $few : $many);

        return $withNumber ? cz_number($n).' '.$word : $word;
    }
}
