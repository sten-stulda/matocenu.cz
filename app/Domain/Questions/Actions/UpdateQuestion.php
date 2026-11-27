<?php

namespace App\Domain\Questions\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Events\QuestionTextChanged;
use App\Models\Question;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/** Edits create a new version; the text is never overwritten without history. */
class UpdateQuestion
{
    public function __construct(private AuditLogger $audit, private CreateQuestion $create) {}

    public function __invoke(User $editor, Question $q, QuestionData $data, ?string $reason = null): Question
    {
        $text = Text::clean($data->text);

        return DB::transaction(function () use ($editor, $q, $data, $text, $reason) {
            $q = Question::lockForUpdate()->findOrFail($q->id);
            $before = $q->only(['text', 'category_id', 'type', 'is_sensitive', 'location_id']);
            $textChanged = $text !== $q->text;

            $q->fill([
                'category_id' => $data->categoryId,
                'location_id' => $data->locationId,
                'type' => $data->type,
                'is_sensitive' => $data->isSensitive,
                'is_anonymous' => $data->isAnonymous,
                'closes_at' => $data->closesAt,
            ]);
            if ($textChanged) {
                $q->text = $text;
                $q->version = $q->version + 1;
                $q->versions()->create(['version' => $q->version, 'text' => $text, 'edited_by' => $editor->id, 'reason' => $reason]);
            }
            $q->save();
            $this->create->syncOptions($q, $data);
            $this->audit->log('question.updated', $q, ['before' => $before, 'after' => $q->only(array_keys($before)), 'reason' => $reason]);
            if ($textChanged) {
                DB::afterCommit(fn () => event(new QuestionTextChanged($q)));
            }

            return $q;
        });
    }
}
