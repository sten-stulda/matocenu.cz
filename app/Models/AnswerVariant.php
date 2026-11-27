<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $question_id
 * @property int|null $language_style_id
 * @property string $input_hash
 * @property string $text
 */
class AnswerVariant extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ai_generation_id', 'user_id', 'question_id', 'language_style_id', 'input_hash', 'text', 'position'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
