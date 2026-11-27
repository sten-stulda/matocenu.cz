<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ai_prompt_id
 * @property int $version
 * @property string $system_template
 * @property string $user_template
 * @property string $status
 * @property AiPrompt $prompt
 */
class AiPromptVersion extends Model
{
    public const STATUSES = ['draft' => 'Koncept', 'review' => 'Ke kontrole', 'approved' => 'Schváleno', 'production' => 'Produkce', 'archived' => 'Archiv'];

    protected $fillable = ['ai_prompt_id', 'version', 'system_template', 'user_template', 'status', 'change_note', 'created_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'production_at' => 'datetime'];
    }

    /** @return BelongsTo<AiPrompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<AiPromptTestResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(AiPromptTestResult::class);
    }
}
