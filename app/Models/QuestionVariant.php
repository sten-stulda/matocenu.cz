<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $question_id
 * @property int $question_version
 * @property string $text
 * @property string $status
 * @property LanguageStyle $style
 */
class QuestionVariant extends Model
{
    protected $fillable = ['question_id', 'question_version', 'language_style_id', 'style_version', 'ai_prompt_version_id', 'ai_generation_id', 'text', 'status'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<LanguageStyle, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(LanguageStyle::class, 'language_style_id');
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
