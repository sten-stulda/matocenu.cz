<?php

namespace App\Models\Concerns;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Models\ModerationAction;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property ModerationStatus $moderation_status
 */
trait Moderatable
{
    /** @param Builder<static> $query */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('moderation_status'), ModerationStatus::publicValues());
    }

    /** @return MorphMany<ModerationAction, $this> */
    public function moderationActions(): MorphMany
    {
        return $this->morphMany(ModerationAction::class, 'moderatable')->latest('created_at');
    }

    /** @return MorphMany<Report, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function moderationStatus(): ModerationStatus
    {
        return $this->moderation_status;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->moderation_status->isPublic() && ! $this->trashed();
    }

    /** Author of the content (used by moderation notifications and policies). */
    abstract public function moderationOwnerId(): ?int;

    /** Short human excerpt for moderation queues. */
    abstract public function moderationExcerpt(): string;
}
