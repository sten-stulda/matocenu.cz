<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['answer_id', 'version', 'original_text', 'display_text', 'is_stylized'];

    protected function casts(): array
    {
        return ['is_stylized' => 'boolean', 'created_at' => 'datetime'];
    }
}
