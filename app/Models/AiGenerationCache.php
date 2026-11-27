<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property list<string> $variants
 * @property string $cost_czk
 */
class AiGenerationCache extends Model
{
    protected $table = 'ai_generation_cache';

    protected $fillable = ['cache_key', 'scope', 'operation', 'variants', 'ai_model_id', 'ai_prompt_version_id', 'cost_czk', 'expires_at'];

    protected function casts(): array
    {
        return ['variants' => 'array', 'last_hit_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
