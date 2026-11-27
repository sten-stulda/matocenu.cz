<?php

namespace App\Models\Contracts;

use App\Domain\Moderation\Enums\ModerationStatus;

/** Content that goes through moderation (questions, answers, comments, needs, offers). */
interface ModeratableContent
{
    public function moderationOwnerId(): ?int;

    public function moderationExcerpt(): string;

    public function moderationStatus(): ModerationStatus;

    public function isPubliclyVisible(): bool;
}
