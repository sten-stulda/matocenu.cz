<?php

namespace App\Domain\Answers\Actions;

use App\Domain\Administration\Services\Settings;
use App\Domain\Answers\Contracts\VariantResolver;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Events\AnswerPublished;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Answers\Services\AnswerLength;
use App\Domain\Moderation\Services\CounterSync;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Questions\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use App\Support\Text;
use Illuminate\Support\Facades\DB;

class SubmitAnswer
{
    public function __construct(
        private AnswerLength $length,
        private Moderation $moderation,
        private CounterSync $counters,
        private Settings $settings,
    ) {}

    public function __invoke(User $user, Question $question, AnswerData $data): Answer
    {
        $original = Text::clean($data->originalText);
        if ($error = $this->length->validate($original)) {
            throw new AnswerException($error);
        }
        if (! $user->isActive()) {
            throw new AnswerException('Účet je pozastavený.');
        }
        if ($this->settings->bool('answers.require_verified_email') && ! $user->hasVerifiedEmail()) {
            throw new AnswerException('Nejdřív si prosím ověř e-mail – pak můžeš odpovídat.');
        }

        return DB::transaction(function () use ($user, $question, $data, $original) {
            // Serialise concurrent submissions of the same user to the same question.
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [1001, crc32($user->id.':'.$question->id) & 0x7FFFFFFF]);
            $question = Question::findOrFail($question->id);
            if (! $question->acceptsAnswers()) {
                throw new AnswerException('Na tuhle otázku už odpovídat nejde.');
            }
            $limit = max(1, $this->settings->int('answers.max_per_question'));
            if (Answer::where('question_id', $question->id)->where('user_id', $user->id)->count() >= $limit) {
                throw new AnswerException($limit === 1 ? 'Na tuhle otázku už jsi odpověděl/a – svou odpověď můžeš upravit.' : "Na tuhle otázku můžeš odpovědět nejvýše {$limit}×.");
            }

            $optionId = null;
            if ($question->type->hasOptions()) {
                $optionId = $question->options()->whereKey($data->optionId)->value('id');
                if (! $optionId) {
                    throw new AnswerException('Vyber jednu z možností.');
                }
            }
            $numeric = null;
            if ($question->type === QuestionType::Numeric) {
                if ($data->numericValue === null || ! is_numeric(str_replace([',', ' '], ['.', ''], $data->numericValue))) {
                    throw new AnswerException('Zadej číslo.');
                }
                $numeric = number_format((float) str_replace([',', ' '], ['.', ''], $data->numericValue), 2, '.', '');
            }

            [$display, $stylized] = $this->resolveDisplay($user, $question, $original, $data);
            [$status, $check] = $this->moderation->initialStatus($original.' '.$display);

            $answer = new Answer([
                'original_text' => $original,
                'display_text' => $display,
                'is_stylized' => $stylized,
                'show_original' => $stylized && $data->showOriginal,
                'is_anonymous' => $data->isAnonymous,
                'option_id' => $optionId,
                'numeric_value' => $numeric,
            ]);
            $answer->question_id = $question->id;
            $answer->user_id = $user->id;
            $answer->moderation_status = $status;
            $answer->published_at = now();
            $answer->save();
            $answer->versions()->create(['version' => 1, 'original_text' => $original, 'display_text' => $display, 'is_stylized' => $stylized]);

            if (! $check['ok']) {
                $this->moderation->recordAutoFlag($answer, $check);
            }
            if ($optionId) {
                DB::table('question_options')->where('id', $optionId)->increment('answers_count');
            }
            $this->counters->question($question->id);
            DB::afterCommit(fn () => event(new AnswerPublished($answer)));

            return $answer;
        });
    }

    /** @return array{0: string, 1: bool} display text and whether it is a stylisation */
    public function resolveDisplay(User $user, Question $question, string $original, AnswerData $data): array
    {
        if ($data->variantId === null) {
            return [$original, false];
        }
        // Etapa C binds an implementation that checks the variant belongs to this user's generation.
        $text = app(VariantResolver::class)->resolve($user, $question, $original, $data->variantId);
        if ($text === null) {
            throw new AnswerException('Zvolená varianta už není k dispozici. Vyber jinou nebo zveřejni originál.');
        }

        return [$text, true];
    }
}
