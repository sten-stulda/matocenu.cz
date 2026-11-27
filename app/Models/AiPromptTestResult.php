<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptTestResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ai_prompt_test_case_id', 'ai_prompt_version_id', 'ai_model_id', 'run_by', 'output', 'passed', 'checks', 'cost_czk'];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'checks' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<AiPromptTestCase, $this> */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(AiPromptTestCase::class, 'ai_prompt_test_case_id');
    }
}
