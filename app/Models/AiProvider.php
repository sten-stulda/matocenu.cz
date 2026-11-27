<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $driver
 * @property string|null $base_url
 * @property string|null $api_key
 * @property bool $is_active
 * @property int $timeout_seconds
 * @property int $max_retries
 */
class AiProvider extends Model
{
    public const DRIVERS = [
        'openrouter' => ['OpenRouter', 'https://openrouter.ai/api/v1'],
        'openai' => ['OpenAI', 'https://api.openai.com/v1'],
        'anthropic' => ['Anthropic', 'https://api.anthropic.com/v1'],
        'gemini' => ['Google Gemini', 'https://generativelanguage.googleapis.com/v1beta'],
        'ollama' => ['Ollama (lokální)', 'http://127.0.0.1:11434/v1'],
        'openai_compatible' => ['OpenAI-kompatibilní server', null],
        'fake' => ['Testovací režim (bez AI)', null],
    ];

    protected $fillable = ['slug', 'name', 'driver', 'base_url', 'is_active', 'timeout_seconds', 'max_retries'];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'is_active' => 'boolean', 'verified_at' => 'datetime'];
    }

    /** @return HasMany<AiModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /** Key from the admin UI, or the env fallback (OPENROUTER_API_KEY …) for the same slug. */
    public function resolvedApiKey(): ?string
    {
        return $this->api_key ?: (config('matocenu.ai.provider_keys.'.$this->slug) ?: null);
    }

    public function maskedKey(): ?string
    {
        $k = $this->resolvedApiKey();

        return $k ? substr($k, 0, 6).'…'.substr($k, -4) : null;
    }

    public function baseUrl(): ?string
    {
        return $this->base_url ?: (self::DRIVERS[$this->driver][1] ?? null);
    }
}
