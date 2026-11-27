<?php

namespace App\Domain\AI\Services;

use App\Support\Text;

/**
 * Cache keys are HMAC-SHA256 of the normalised inputs, so the key reveals nothing about the
 * (possibly sensitive) text and cannot be brute-forced without the secret.
 */
class CacheKey
{
    /** @param array<string, scalar|null> $parts */
    public static function make(array $parts): string
    {
        ksort($parts);

        return hash_hmac('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE), self::secret());
    }

    public static function inputHash(string $text): string
    {
        return hash_hmac('sha256', Text::clean($text), self::secret());
    }

    private static function secret(): string
    {
        return (string) (config('matocenu.ai.cache_hmac_key') ?: config('app.key'));
    }

    /** Short, generic answers ("Ano", "Já", "Nevím") may be shared across users of the same question. */
    public static function isShareable(string $original): bool
    {
        return in_array(Text::fold($original), array_map(fn ($s) => Text::fold($s), config('matocenu.ai.shared_cache_answers')), true);
    }
}
