<?php

namespace App\Domain\Questions\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Administration\Services\Settings;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Questions\DTO\QuestionData;
use App\Domain\Questions\Enums\PublicationStatus;
use App\Domain\Questions\Enums\QuestionType;
use App\Domain\Questions\Services\SlugGenerator;
use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

class CreateQuestion
{
    public function __construct(private SlugGenerator $slugs, private Moderation $moderation, private Settings $settings, private AuditLogger $audit) {}

    /**
     * Editors create questions as drafts (they publish/schedule them explicitly).
     * Community questions are published immediately or wait for approval, per settings.
     */
    public function __invoke(User $author, QuestionData $data, bool $asEditor = false): Question
    {
        $text = Text::clean($data->text);

        return DB::transaction(function () use ($author, $data, $text, $asEditor) {
            $q = new Question([
                'category_id' => $data->categoryId,
                'location_id' => $data->locationId,
                'type' => $data->type,
                'text' => $text,
                'closes_at' => $data->closesAt,
                'is_sensitive' => $data->isSensitive || (bool) Category::whereKey($data->categoryId)->value('is_sensitive'),
                'is_anonymous' => $data->isAnonymous,
            ]);
            $q->author_id = $author->id;
            $q->slug = $this->slugs->forQuestion($text);

            if ($asEditor) {
                $q->moderation_status = ModerationStatus::Approved;
                $q->publication_status = PublicationStatus::Draft;
                $check = null;
            } else {
                [$status, $check] = $this->moderation->initialStatus($text, $this->settings->bool('questions.user_questions_need_approval'));
                $q->moderation_status = $status;
                $q->publication_status = PublicationStatus::Published;
                $q->published_at = now();
            }
            $q->last_activity_at = now();
            $q->save();

            $q->versions()->create(['version' => 1, 'text' => $text, 'edited_by' => $author->id, 'reason' => 'Vytvoření']);
            $this->syncOptions($q, $data);
            if ($check && ! $check['ok']) {
                $this->moderation->recordAutoFlag($q, $check);
            }
            if ($asEditor) {
                $this->audit->log('question.created', $q);
            }

            return $q;
        });
    }

    public function syncOptions(Question $q, QuestionData $data): void
    {
        if ($data->type === QuestionType::YesNo) {
            $labels = ['Ano', 'Ne'];
        } elseif ($data->type->hasOptions()) {
            $labels = array_slice(array_values(array_unique(array_map(fn ($o) => Text::clean($o), $data->options))), 0, 8);
        } else {
            $labels = [];
        }
        if ($q->options()->exists() && $q->answers()->exists()) {
            return; // never change options once people answered
        }
        $q->options()->delete();
        foreach ($labels as $i => $label) {
            $q->options()->create(['label' => $label, 'sort_order' => $i]);
        }
    }
}
