<?php

namespace App\Domain\Comments\Actions;

use App\Domain\Administration\Services\Settings;
use App\Domain\Comments\Events\CommentPosted;
use App\Domain\Comments\Exceptions\CommentException;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\CounterSync;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Comment;
use App\Models\Contracts\ModeratableContent;
use App\Models\User;
use App\Support\Text;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommentActions
{
    public function __construct(private Settings $settings, private Moderation $moderation, private CounterSync $counters) {}

    public function add(User $user, Model&ModeratableContent $target, string $body, ?Comment $parent = null): Comment
    {
        $body = Text::clean($body, multiline: true);
        $max = $this->settings->int('comments.max_length');
        if (Text::length($body) < 2) {
            throw new CommentException('Komentář je prázdný.');
        }
        if (Text::length($body) > $max) {
            throw new CommentException("Komentář může mít nejvýše {$max} znaků.");
        }
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            throw new CommentException('Komentovat mohou uživatelé s ověřeným e-mailem.');
        }
        if (! $target->isPubliclyVisible()) {
            throw new CommentException('K tomuto příspěvku už komentovat nelze.');
        }
        $depth = 0;
        if ($parent) {
            if ($parent->commentable_type !== $target->getMorphClass() || (int) $parent->commentable_id !== (int) $target->getKey()) {
                throw new CommentException('Neplatné vlákno.');
            }
            $depth = $parent->depth + 1;
            if ($depth > $this->settings->int('comments.max_depth')) {
                // Too deep → attach to the deepest allowed ancestor instead of nesting forever.
                $depth = $parent->depth;
                $parent = $parent->parent ?? $parent;
            }
        }
        foreach (array_filter([$target->moderationOwnerId(), $parent?->user_id]) as $ownerId) {
            if ($ownerId !== $user->id && DB::table('user_blocks')->where('blocker_id', $ownerId)->where('blocked_id', $user->id)->exists()) {
                throw new CommentException('Tento uživatel si nepřeje tvé komentáře.');
            }
        }

        return DB::transaction(function () use ($user, $target, $body, $parent, $depth) {
            [$status, $check] = $this->moderation->initialStatus($body);
            $c = new Comment(['body' => $body]);
            $c->commentable_type = $target->getMorphClass();
            $c->commentable_id = $target->getKey();
            $c->user_id = $user->id;
            $c->parent_id = $parent?->id;
            $c->depth = $depth;
            $c->moderation_status = $status;
            $c->save();
            if (! $check['ok']) {
                $this->moderation->recordAutoFlag($c, $check);
            }
            $this->counters->afterVisibilityChange($c);
            if ($status === ModerationStatus::Approved) {
                DB::afterCommit(fn () => event(new CommentPosted($c)));
            }

            return $c;
        });
    }

    public function edit(User $user, Comment $comment, string $body): Comment
    {
        if ($comment->user_id !== $user->id) {
            throw new CommentException('Upravit můžeš jen svůj komentář.');
        }
        $body = Text::clean($body, multiline: true);
        if (Text::length($body) < 2 || Text::length($body) > $this->settings->int('comments.max_length')) {
            throw new CommentException('Neplatná délka komentáře.');
        }
        [$status, $check] = $this->moderation->initialStatus($body);
        $comment->body = $body;
        $comment->edited_at = now();
        if (! $check['ok']) {
            $comment->moderation_status = $status;
        }
        $comment->save();
        if (! $check['ok']) {
            $this->moderation->recordAutoFlag($comment, $check);
            $this->counters->afterVisibilityChange($comment);
        }

        return $comment;
    }

    public function delete(User $user, Comment $comment): void
    {
        if ($comment->user_id !== $user->id && ! $user->can('comments.manage')) {
            throw new CommentException('Smazat můžeš jen svůj komentář.');
        }
        $comment->delete();
        $this->counters->afterVisibilityChange($comment);
    }
}
