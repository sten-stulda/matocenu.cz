<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Needs\Services\NeedCounters;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Need;
use App\Models\NeedOffer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Denormalised counters are recomputed from source rows (never blindly incremented),
 * so concurrent requests and moderation changes cannot make them drift.
 */
class CounterSync
{
    public function question(int $questionId): void
    {
        $visible = ModerationStatus::publicValues();
        DB::table('questions')->where('id', $questionId)->update([
            'answers_count' => Answer::where('question_id', $questionId)->whereIn('moderation_status', $visible)->count(),
            'comments_count' => Comment::where('commentable_type', 'question')->where('commentable_id', $questionId)->whereIn('moderation_status', $visible)->count(),
            'reactions_count' => DB::table('reactions')->join('answers', 'answers.id', '=', 'reactions.reactable_id')
                ->where('reactions.reactable_type', 'answer')->where('answers.question_id', $questionId)
                ->whereNull('answers.deleted_at')->whereIn('answers.moderation_status', $visible)->count(),
            'followers_count' => DB::table('follows')->where('followable_type', 'question')->where('followable_id', $questionId)->count(),
            'last_activity_at' => now(),
        ]);
    }

    public function answer(int $answerId): void
    {
        $visible = ModerationStatus::publicValues();
        DB::table('answers')->where('id', $answerId)->update([
            'comments_count' => Comment::where('commentable_type', 'answer')->where('commentable_id', $answerId)->whereIn('moderation_status', $visible)->count(),
        ]);
    }

    public function afterVisibilityChange(Model $model): void
    {
        if ($model instanceof Question) {
            $this->question($model->id);
        } elseif ($model instanceof Answer) {
            $this->question($model->question_id);
        } elseif ($model instanceof Need) {
            app(NeedCounters::class)->need($model->id);
        } elseif ($model instanceof NeedOffer) {
            app(NeedCounters::class)->need($model->need_id);
        } elseif ($model instanceof Comment) {
            $target = $model->commentable_type;
            if ($target === 'answer') {
                $this->answer((int) $model->commentable_id);
                $qid = Answer::withTrashed()->whereKey($model->commentable_id)->value('question_id');
                if ($qid) {
                    $this->question((int) $qid);
                }
            } elseif ($target === 'question') {
                $this->question((int) $model->commentable_id);
            }
        }
    }
}
