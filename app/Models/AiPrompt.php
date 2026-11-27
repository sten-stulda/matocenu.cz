<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property list<string> $variables
 */
class AiPrompt extends Model
{
    protected $fillable = ['key', 'name', 'purpose', 'variables'];

    protected function casts(): array
    {
        return ['variables' => 'array'];
    }

    /** @return HasMany<AiPromptVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AiPromptVersion::class)->orderByDesc('version');
    }

    /** @return HasMany<AiPromptTestCase, $this> */
    public function testCases(): HasMany
    {
        return $this->hasMany(AiPromptTestCase::class);
    }

    public function production(): ?AiPromptVersion
    {
        return $this->versions()->where('status', 'production')->first();
    }
}
