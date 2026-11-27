<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property string $status
 * @property Question $question
 */
class DailyQuestion extends Model
{
    public const STATUSES = ['planned' => 'návrh', 'approved' => 'schváleno', 'published' => 'zveřejněno', 'skipped' => 'vynecháno'];

    protected $fillable = ['date', 'question_id', 'status', 'planned_by', 'published_at', 'evaluated_at', 'evaluation'];

    protected function casts(): array
    {
        return ['date' => 'date', 'published_at' => 'datetime', 'evaluated_at' => 'datetime', 'evaluation' => 'array'];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
