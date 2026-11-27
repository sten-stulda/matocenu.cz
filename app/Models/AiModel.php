<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $model
 * @property string $name
 * @property string|null $input_price_per_mtok
 * @property string|null $output_price_per_mtok
 * @property int $max_output_tokens
 * @property string|null $temperature
 * @property bool $supports_temperature
 * @property bool $is_primary
 * @property bool $is_fallback
 * @property bool $is_active
 * @property AiProvider $provider
 */
class AiModel extends Model
{
    protected $fillable = ['ai_provider_id', 'model', 'name', 'input_price_per_mtok', 'output_price_per_mtok', 'currency', 'max_output_tokens', 'temperature', 'supports_temperature', 'is_active'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'is_fallback' => 'boolean', 'is_active' => 'boolean', 'supports_temperature' => 'boolean', 'prices_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<AiProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function hasPrices(): bool
    {
        return $this->input_price_per_mtok !== null && $this->output_price_per_mtok !== null;
    }
}
