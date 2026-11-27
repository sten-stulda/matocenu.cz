<?php

namespace App\Listeners;

use App\Domain\Answers\Events\AnswerPublished;
use App\Domain\Gamification\Services\Achievements;
use App\Domain\Reactions\Events\ReactionAdded;
use App\Models\Answer;
use App\Models\User;
use Illuminate\Events\Dispatcher;

class GamificationListener
{
    public function __construct(private Achievements $achievements) {}

    public function answerPublished(AnswerPublished $e): void
    {
        $this->achievements->evaluate($e->answer->user()->first() ?? new User);
    }

    public function reactionAdded(ReactionAdded $e): void
    {
        if ($e->target instanceof Answer && ($owner = User::find($e->target->user_id))) {
            $this->achievements->evaluate($owner);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [AnswerPublished::class => 'answerPublished', ReactionAdded::class => 'reactionAdded'];
    }
}
