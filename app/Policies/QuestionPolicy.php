<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function view(?User $user, Question $question): bool
    {
        if ($question->isVisible()) {
            return true;
        }

        // Authors see their own pending questions, editors/moderators everything.
        return $user && ($user->id === $question->author_id || $user->can('questions.manage') || $user->can('moderation.manage'));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->can('questions.manage');
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->can('questions.manage') || ($user->id === $question->author_id && $question->answers_count === 0);
    }
}
