<?php

namespace App\Domain\Moderation\Services;

use App\Models\Answer;
use App\Models\Comment;
use App\Models\Need;
use App\Models\NeedOffer;
use App\Models\Question;

class QueueCount
{
    public const STATUSES = ['pending', 'under_review', 'reported', 'hidden'];

    public function __invoke(): int
    {
        $s = ['pending', 'under_review', 'reported'];

        return Question::whereIn('moderation_status', $s)->count()
            + Answer::whereIn('moderation_status', $s)->count()
            + Comment::whereIn('moderation_status', $s)->count()
            + Need::whereIn('moderation_status', $s)->count()
            + NeedOffer::whereIn('moderation_status', $s)->count();
    }
}
