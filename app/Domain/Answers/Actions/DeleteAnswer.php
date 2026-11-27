<?php

namespace App\Domain\Answers\Actions;

use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Moderation\Services\CounterSync;
use App\Models\Answer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteAnswer
{
    public function __construct(private CounterSync $counters) {}

    public function __invoke(User $user, Answer $answer): void
    {
        if ($answer->user_id !== $user->id && ! $user->can('answers.manage')) {
            throw new AnswerException('Smazat můžeš jen svou odpověď.');
        }
        DB::transaction(function () use ($answer) {
            if ($answer->option_id) {
                DB::table('question_options')->where('id', $answer->option_id)->where('answers_count', '>', 0)->decrement('answers_count');
            }
            $answer->delete();
            $this->counters->question($answer->question_id);
        });
    }
}
