<?php

namespace App\Domain\Questions\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Exceptions\QuestionException;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Editorial publication workflow of a question (separate from moderation status). */
class ChangePublication
{
    public function __construct(private Moderation $moderation, private AuditLogger $audit) {}

    public function __invoke(User $editor, Question $q, PublicationStatus $to): Question
    {
        if (! $editor->can('questions.manage')) {
            throw new QuestionException('Nemáš oprávnění.');
        }

        return DB::transaction(function () use ($editor, $q, $to) {
            $q = Question::lockForUpdate()->findOrFail($q->id);
            $from = $q->publication_status;
            if ($to === PublicationStatus::Published) {
                if (! $q->moderation_status->isPublic()) {
                    // Publishing by an editor = explicit approval.
                    $this->moderation->transition($q, 'approve', ModerationStatus::Approved, $editor, 'Schváleno při zveřejnění');
                    $q->refresh();
                }
                $q->published_at ??= now();
            }
            $q->publication_status = $to;
            $q->save();
            $this->audit->log('question.publication', $q, ['from' => $from->value, 'to' => $to->value], $editor->id);

            return $q;
        });
    }
}
