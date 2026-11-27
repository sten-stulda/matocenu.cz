<?php

namespace App\Domain\Answers\Actions;

use App\Domain\Administration\Services\Settings;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Answers\Services\AnswerLength;
use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\CounterSync;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Answer;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

/** Author edits create a new version; reactions stay (the thought is the same person's). */
class UpdateAnswer
{
    public function __construct(private AnswerLength $length, private Moderation $moderation, private SubmitAnswer $submit, private CounterSync $counters, private Settings $settings) {}

    public function __invoke(User $user, Answer $answer, AnswerData $data): Answer
    {
        if ($answer->user_id !== $user->id) {
            throw new AnswerException('Upravit můžeš jen svou odpověď.');
        }
        if (in_array($answer->moderation_status, [ModerationStatus::Hidden, ModerationStatus::Rejected], true)) {
            throw new AnswerException('Skrytou odpověď nelze upravit – můžeš se odvolat.');
        }
        $window = $this->settings->int('answers.edit_window_minutes');
        if ($window > 0 && $answer->published_at && $answer->published_at->lt(now()->subMinutes($window))) {
            throw new AnswerException("Odpověď lze upravit jen {$window} minut po zveřejnění.");
        }
        $original = Text::clean($data->originalText);
        if ($error = $this->length->validate($original)) {
            throw new AnswerException($error);
        }

        return DB::transaction(function () use ($user, $answer, $data, $original) {
            $answer = Answer::lockForUpdate()->findOrFail($answer->id);
            [$display, $stylized] = $this->submit->resolveDisplay($user, $answer->question, $original, $data);
            [$status, $check] = $this->moderation->initialStatus($original.' '.$display);

            $answer->fill([
                'original_text' => $original,
                'display_text' => $display,
                'is_stylized' => $stylized,
                'show_original' => $stylized && $data->showOriginal,
                'is_anonymous' => $data->isAnonymous,
            ]);
            $answer->version++;
            $answer->edited_at = now();
            if (! $check['ok']) {
                $answer->moderation_status = $status;
            }
            $answer->save();
            $answer->versions()->create(['version' => $answer->version, 'original_text' => $original, 'display_text' => $display, 'is_stylized' => $stylized]);
            if (! $check['ok']) {
                $this->moderation->recordAutoFlag($answer, $check);
            }
            $this->counters->question($answer->question_id);

            return $answer;
        });
    }
}
