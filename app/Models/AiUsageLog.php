<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ai_generation_id', 'user_id', 'provider', 'model', 'operation', 'ai_prompt_version_id', 'input_tokens', 'output_tokens', 'extra_tokens', 'cost_usd', 'cost_czk', 'cost_is_estimate', 'saved_czk', 'duration_ms', 'cache_hit', 'status', 'error_type'];

    protected function casts(): array
    {
        return ['cache_hit' => 'boolean', 'cost_is_estimate' => 'boolean', 'created_at' => 'datetime'];
    }
}
