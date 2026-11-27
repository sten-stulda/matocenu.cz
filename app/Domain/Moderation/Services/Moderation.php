<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Events\ContentModerated;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Models\Question;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for moderation state changes: writes history, keeps counters
 * consistent, resolves reports and informs the author.
 */
class Moderation
{
    public function __construct(private CounterSync $counters, private AuditLogger $audit) {}

    /** @param Question|Answer|Comment $model */
    public function transition(Model $model, string $action, ModerationStatus $to, ?User $moderator = null, ?string $reason = null, ?string $risk = null): ModerationAction
    {
        return DB::transaction(function () use ($model, $action, $to, $moderator, $reason, $risk) {
            $model->refresh();
            $from = $model->moderation_status;
            $model->moderation_status = $to;
            $model->save();

            $entry = ModerationAction::create([
                'moderatable_type' => $model->getMorphClass(),
                'moderatable_id' => $model->getKey(),
                'moderator_id' => $moderator?->id,
                'action' => $action,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'reason' => $reason,
                'risk' => $risk,
            ]);

            if (in_array($to, [ModerationStatus::Approved, ModerationStatus::Hidden, ModerationStatus::Rejected], true) && $moderator) {
                Report::where('reportable_type', $model->getMorphClass())->where('reportable_id', $model->getKey())
                    ->where('status', 'open')
                    ->update(['status' => $to === ModerationStatus::Approved ? 'dismissed' : 'resolved', 'resolved_by' => $moderator->id, 'resolved_at' => now()]);
            }

            $this->counters->afterVisibilityChange($model);
            if ($moderator) {
                $this->audit->log('moderation.'.$action, $model, ['from' => $from?->value, 'to' => $to->value, 'reason' => $reason], $moderator->id);
            }
            DB::afterCommit(fn () => event(new ContentModerated($entry)));

            return $entry;
        });
    }

    /** Status for new content after the automatic check. */
    public function initialStatus(string $text, bool $needsApproval = false): array
    {
        $check = app(ContentChecker::class)->check($text);
        if (! $check['ok']) {
            return [ModerationStatus::UnderReview, $check];
        }

        return [$needsApproval ? ModerationStatus::Pending : ModerationStatus::Approved, $check];
    }

    /** Record the automatic flag in history so the author can appeal. */
    /** @param array{ok: bool, flags: list<string>, risk: string|null, reason: string|null} $check */
    public function recordAutoFlag(Question|Answer|Comment $model, array $check): void
    {
        ModerationAction::create([
            'moderatable_type' => $model->getMorphClass(),
            'moderatable_id' => $model->getKey(),
            'moderator_id' => null,
            'action' => 'auto_flag',
            'from_status' => null,
            'to_status' => $model->moderation_status->value,
            'reason' => $check['reason'],
            'risk' => $check['risk'],
        ]);
    }
}
