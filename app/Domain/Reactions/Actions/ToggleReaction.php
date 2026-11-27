<?php

namespace App\Domain\Reactions\Actions;

use App\Domain\Administration\Services\Settings;
use App\Domain\Moderation\Services\CounterSync;
use App\Domain\Rankings\Services\AnswerScore;
use App\Domain\Reactions\Events\ReactionAdded;
use App\Domain\Reactions\Exceptions\ReactionException;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\ReactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One reaction per user per item (unique key). Clicking the same reaction removes it,
 * clicking another one changes it. Counters are recomputed from rows after every change.
 */
class ToggleReaction
{
    public function __construct(private Settings $settings, private AnswerScore $score) {}

    /** @param Answer|Comment $target
     * @return string|null the user's reaction slug after the change */
    public function __invoke(User $user, Model $target, string $typeSlug): ?string
    {
        $type = ReactionType::active()->firstWhere('slug', $typeSlug);
        if (! $type) {
            throw new ReactionException('Neznámá reakce.');
        }
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            throw new ReactionException('Reagovat mohou přihlášení uživatelé s ověřeným e-mailem.');
        }
        if ($target->moderationOwnerId() === $user->id) {
            throw new ReactionException('Vlastní příspěvek hodnotit nemůžeš.');
        }
        if (! $target->isPubliclyVisible()) {
            throw new ReactionException('Na tento příspěvek už reagovat nelze.');
        }
        $ownerId = $target->moderationOwnerId();
        if ($ownerId && DB::table('user_blocks')->where('blocker_id', $ownerId)->where('blocked_id', $user->id)->exists()) {
            throw new ReactionException('Autor si nepřeje tvé reakce.');
        }

        $allowChange = $this->settings->bool('reactions.allow_change');
        $key = ['user_id' => $user->id, 'reactable_type' => $target->getMorphClass(), 'reactable_id' => $target->getKey()];

        $result = DB::transaction(function () use ($key, $type, $allowChange) {
            $existing = Reaction::where($key)->lockForUpdate()->first();
            if ($existing) {
                if (! $allowChange) {
                    throw new ReactionException('Reakci už nelze změnit.');
                }
                if ($existing->reaction_type_id === $type->id) {
                    $existing->delete();

                    return [null, false];
                }
                $existing->update(['reaction_type_id' => $type->id]);

                return [$type->slug, false];
            }
            // Concurrent double-click: the unique key makes the second insert a no-op.
            $inserted = DB::table('reactions')->insertOrIgnore($key + ['reaction_type_id' => $type->id, 'created_at' => now(), 'updated_at' => now()]);

            return [$type->slug, $inserted > 0];
        });

        $this->recount($target);
        if ($result[1]) {
            DB::afterCommit(fn () => event(new ReactionAdded($user, $target, $type)));
        }

        return $result[0];
    }

    public function recount(Model $target): void
    {
        $count = Reaction::where('reactable_type', $target->getMorphClass())->where('reactable_id', $target->getKey())->count();
        if ($target instanceof Answer) {
            $unique = Reaction::where('reactable_type', 'answer')->where('reactable_id', $target->id)->distinct()->count('user_id');
            DB::table('answers')->where('id', $target->id)->update([
                'reactions_count' => $count,
                'unique_reactors_count' => $unique,
                'score' => $this->score->compute($target->id),
            ]);
            app(CounterSync::class)->question($target->question_id);
        } elseif ($target instanceof Comment) {
            DB::table('comments')->where('id', $target->id)->update(['reactions_count' => $count]);
        }
    }
}
