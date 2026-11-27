<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $variables
 * @property array<string, mixed> $expectations
 */
class AiPromptTestCase extends Model
{
    protected $fillable = ['ai_prompt_id', 'name', 'variables', 'expectations', 'is_active'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'expectations' => 'array', 'is_active' => 'boolean'];
    }
}
