<?php

namespace App\Domain\Questions\Services;

use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AnswerListing
{
    /** @return LengthAwarePaginator<int, Answer> */
    public function forQuestion(Question $question, ?User $viewer, string $sort = 'nejlepsi', int $perPage = 20): LengthAwarePaginator
    {
        $region = $sort === 'kraj' ? $viewer?->location?->region() : null;

        return Answer::query()->with(['user', 'question'])
            ->where('question_id', $question->id)->publiclyVisible()
            // Content of people the viewer blocked is not shown to them.
            ->when($viewer, fn ($q) => $q->whereNotIn('user_id', DB::table('user_blocks')->where('blocker_id', $viewer->id)->select('blocked_id')))
            ->when($region, fn ($q) => $q->where('is_anonymous', false)->whereIn('user_id', User::whereIn('location_id', $region->descendantIds())->select('id')))
            ->when($sort === 'nove', fn ($q) => $q->latest('published_at'), fn ($q) => $q->orderByDesc('score')->latest('published_at'))
            ->paginate($perPage, pageName: 'strana')->withQueryString();
    }
}
