<?php

namespace App\Domain\Administration\Services;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

class FeatureFlags
{
    private const CACHE_KEY = 'mtc:features:v1';

    public function enabled(string $key): bool
    {
        $def = config("matocenu.features.$key");
        if ($def === null) {
            return false;
        }
        // Modules that are not publicly ready can never be on, whatever the DB says.
        if (! $def[3]) {
            return false;
        }
        $flags = Cache::remember(self::CACHE_KEY, 300, fn () => FeatureFlag::query()->pluck('enabled', 'key')->all());

        return (bool) ($flags[$key] ?? $def[2]);
    }

    public function isReady(string $key): bool
    {
        return (bool) (config("matocenu.features.$key")[3] ?? false);
    }

    public function set(string $key, bool $enabled, ?int $userId = null): void
    {
        $def = config("matocenu.features.$key");
        abort_if($def === null, 404);
        abort_if($enabled && ! $def[3], 422, 'Modul není dokončený, nelze jej zapnout.');
        $flag = FeatureFlag::updateOrCreate(['key' => $key], ['name' => $def[0], 'description' => $def[1], 'enabled' => $enabled, 'updated_by' => $userId]);
        Cache::forget(self::CACHE_KEY);
        app(AuditLogger::class)->log('feature.toggled', $flag, ['key' => $key, 'enabled' => $enabled]);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
