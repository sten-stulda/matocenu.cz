<?php

namespace App\Policies;

use App\Models\Answer;
use App\Models\User;

class AnswerPolicy
{
    public function view(?User $user, Answer $answer): bool
    {
        if ($answer->isPubliclyVisible() && $answer->question?->isVisible()) {
            return true;
        }

        return $user && ($user->id === $answer->user_id || $user->can('answers.manage') || $user->can('moderation.manage'));
    }

    public function update(User $user, Answer $answer): bool
    {
        return $user->id === $answer->user_id;
    }

    public function delete(User $user, Answer $answer): bool
    {
        return $user->id === $answer->user_id || $user->can('answers.manage');
    }
}
