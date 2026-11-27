<?php

namespace App\Models;

use App\Domain\Moderation\Enums\ReportReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property ReportReason $reason
 * @property string $status
 */
class Report extends Model
{
    protected $fillable = ['reporter_id', 'reportable_type', 'reportable_id', 'reason', 'note', 'status', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['reason' => ReportReason::class, 'resolved_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
