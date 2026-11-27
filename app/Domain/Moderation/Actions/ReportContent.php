<?php

namespace App\Domain\Moderation\Actions;

use App\Domain\Administration\Services\Settings;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Contracts\ModeratableContent;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReportContent
{
    public function __construct(private Moderation $moderation, private Settings $settings) {}

    public function __invoke(User $reporter, Model&ModeratableContent $target, ReportReason $reason, ?string $note = null): Report
    {
        if ($target->moderationOwnerId() === $reporter->id) {
            throw new ModerationException('Vlastní příspěvek nahlásit nemůžeš – můžeš ho upravit nebo smazat.');
        }

        try {
            $report = Report::create([
                'reporter_id' => $reporter->id,
                'reportable_type' => $target->getMorphClass(),
                'reportable_id' => $target->getKey(),
                'reason' => $reason,
                'note' => $note ? mb_substr(trim($note), 0, 500) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ModerationException('Tento příspěvek už jsi nahlásil/a. Moderátor se na něj podívá.');
        }

        DB::transaction(function () use ($target, $reason) {
            $target->refresh();
            $distinct = Report::where('reportable_type', $target->getMorphClass())->where('reportable_id', $target->getKey())
                ->where('status', 'open')->distinct()->count('reporter_id');
            $threshold = max(1, $this->settings->int('moderation.auto_hide_reports'));

            if (in_array($target->moderationStatus(), [ModerationStatus::Approved, ModerationStatus::Reported], true)) {
                if ($distinct >= $threshold) {
                    $this->moderation->transition($target, 'auto_hide', ModerationStatus::Hidden, null, "Nahlášeno {$distinct}× – čeká na moderátora", $reason->risk());
                } elseif ($target->moderationStatus() === ModerationStatus::Approved) {
                    $target->forceFill(['moderation_status' => ModerationStatus::Reported])->save();
                }
            }
        });

        return $report;
    }
}
