<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $status
 * @property bool $cache_hit
 * @property string|null $error
 */
class AiGeneration extends Model
{
    protected $fillable = ['user_id', 'operation', 'question_id', 'language_style_id', 'style_version', 'ai_prompt_version_id', 'ai_model_id', 'input_hash', 'cache_key', 'cache_hit', 'is_regeneration', 'status', 'error'];

    protected $attributes = ['cache_hit' => false, 'is_regeneration' => false];

    protected function casts(): array
    {
        return ['cache_hit' => 'boolean', 'is_regeneration' => 'boolean'];
    }

    /** @return HasMany<AnswerVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(AnswerVariant::class)->orderBy('position');
    }

    /** @return BelongsTo<LanguageStyle, $this> */
    public function style(): BelongsTo
    {
        return $this->belongsTo(LanguageStyle::class, 'language_style_id');
    }
}
