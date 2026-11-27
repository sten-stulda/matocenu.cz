<?php

namespace App\Listeners;

use App\Domain\Answers\Events\AnswerPublished;
use App\Domain\Comments\Events\CommentPosted;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Events\ContentModerated;
use App\Domain\Questions\Events\DailyQuestionPublished;
use App\Domain\Reactions\Events\ReactionAdded;
use App\Models\Answer;
use App\Models\User;
use App\Notifications\CommentReceived;
use App\Notifications\DailyQuestionReady;
use App\Notifications\ModerationDecision;
use App\Notifications\QuestionAnswered;
use App\Notifications\ReactionsReceived;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;

/** Maps domain events to user notifications. Anonymous authors stay anonymous in every message. */
class CommunityNotifications
{
    public function answerPublished(AnswerPublished $e): void
    {
        $answer = $e->answer->fresh(['question.author', 'user']);
        if (! $answer || ! $answer->moderation_status->isPublic()) {
            return;
        }
        $author = $answer->question->author;
        if ($author && $author->id !== $answer->user_id && $author->isActive()) {
            // At most one "new answer" notification per question per hour.
            if (Cache::add('mtc:notify:qa:'.$answer->question_id, 1, 3600)) {
                $author->notify(new QuestionAnswered($answer));
            }
        }
    }

    public function commentPosted(CommentPosted $e): void
    {
        $c = $e->comment->fresh(['user', 'commentable', 'parent.user']);
        if (! $c) {
            return;
        }
        $notified = [];
        if ($c->parent && $c->parent->user_id !== $c->user_id && $c->parent->user->isActive()) {
            $c->parent->user->notify(new CommentReceived($c, true));
            $notified[] = $c->parent->user_id;
        }
        $ownerId = $c->target()?->moderationOwnerId();
        if ($ownerId && $ownerId !== $c->user_id && ! in_array($ownerId, $notified, true) && ($owner = User::find($ownerId)) && $owner->isActive()) {
            $owner->notify(new CommentReceived($c));
        }
    }

    public function reactionAdded(ReactionAdded $e): void
    {
        if (! $e->target instanceof Answer) {
            return;
        }
        $answer = $e->target->fresh('user');
        // Summarised: one notification per answer per 6 hours.
        if ($answer && $answer->user->isActive() && Cache::add('mtc:notify:re:'.$answer->id, 1, 6 * 3600)) {
            $answer->user->notify(new ReactionsReceived($answer, $e->type->label));
        }
    }

    public function contentModerated(ContentModerated $e): void
    {
        $action = $e->action;
        if (! in_array($action->action, ['approve', 'reject', 'hide', 'auto_hide', 'appeal_upheld', 'appeal_overturned'], true)) {
            return;
        }
        if ($action->action === 'approve' && $action->from_status === ModerationStatus::Reported->value) {
            return; // reports dismissed – nothing changed for the author
        }
        $ownerId = $action->content()?->moderationOwnerId();
        if ($ownerId && ($owner = User::find($ownerId)) && $owner->isActive()) {
            $owner->notify(new ModerationDecision($action));
        }
    }

    public function dailyPublished(DailyQuestionPublished $e): void
    {
        $entry = $e->entry->load('question');
        User::query()->where('status', 'active')->whereNotNull('email_verified_at')
            ->chunkById(500, function ($users) use ($entry) {
                foreach ($users as $u) {
                    $u->notify(new DailyQuestionReady($entry));
                }
            });
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            AnswerPublished::class => 'answerPublished',
            CommentPosted::class => 'commentPosted',
            ReactionAdded::class => 'reactionAdded',
            ContentModerated::class => 'contentModerated',
            DailyQuestionPublished::class => 'dailyPublished',
        ];
    }
}
