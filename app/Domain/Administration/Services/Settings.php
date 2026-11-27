<?php

namespace App\Domain\Administration\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed application settings: DB overrides on top of config('matocenu.settings') defaults.
 * Cached as one array; flushed on every write.
 */
class Settings
{
    private const CACHE_KEY = 'mtc:settings:v1';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    /** Decimal settings are kept as strings (exact money arithmetic via bcmath). */
    public function decimal(string $key): string
    {
        return number_format((float) $this->get($key), 4, '.', '');
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $v = $this->get($key);

        return is_array($v) ? array_values(array_filter(array_map('strval', $v), fn ($s) => trim($s) !== '')) : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $overrides = Cache::remember(self::CACHE_KEY, 300, fn () => Setting::query()->pluck('value', 'key')->all());

        $values = [];
        foreach (config('matocenu.settings') as $key => [$default]) {
            $values[$key] = array_key_exists($key, $overrides) ? $overrides[$key] : $default;
        }

        return $this->loaded = $values;
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $before = $this->get($key);
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        $this->flush();
        app(AuditLogger::class)->log('settings.updated', null, ['key' => $key, 'before' => $before, 'after' => $value]);
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
