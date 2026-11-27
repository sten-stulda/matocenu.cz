<?php

namespace App\Domain\Moderation\Actions;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Domain\Moderation\Services\Moderation;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AppealDecision
{
    public function __construct(private Moderation $moderation) {}

    public function file(User $user, ModerationAction $action, string $message): ModerationAppeal
    {
        $target = $action->content();
        if (! $target || $target->moderationOwnerId() !== $user->id) {
            throw new ModerationException('Odvolat se může jen autor příspěvku.');
        }
        if (! in_array($action->to_status, ['rejected', 'hidden', 'under_review'], true)) {
            throw new ModerationException('Proti tomuto rozhodnutí se nelze odvolat.');
        }
        try {
            return ModerationAppeal::create(['moderation_action_id' => $action->id, 'user_id' => $user->id, 'message' => mb_substr(trim($message), 0, 1000)]);
        } catch (UniqueConstraintViolationException) {
            throw new ModerationException('Odvolání už jsi podal/a.');
        }
    }

    public function decide(User $moderator, ModerationAppeal $appeal, bool $overturn, ?string $note): void
    {
        if ($appeal->status !== 'open') {
            throw new ModerationException('Odvolání už bylo rozhodnuto.');
        }
        if ($appeal->action->moderator_id === $moderator->id && ! $moderator->hasRole('superadmin')) {
            throw new ModerationException('O odvolání musí rozhodnout jiný moderátor než ten, kdo zasáhl.');
        }
        DB::transaction(function () use ($moderator, $appeal, $overturn, $note) {
            $appeal->update(['status' => $overturn ? 'overturned' : 'upheld', 'decided_by' => $moderator->id, 'decision_note' => $note, 'decided_at' => now()]);
            $target = $appeal->action->content();
            if ($target) {
                $this->moderation->transition($target, $overturn ? 'appeal_overturned' : 'appeal_upheld',
                    $overturn ? ModerationStatus::Approved : $target->moderation_status, $moderator, $note);
            }
        });
    }
}
