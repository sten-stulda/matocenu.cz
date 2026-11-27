<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Bookmark;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function mine(Request $request): RedirectResponse
    {
        return redirect()->route('profiles.show', $request->user());
    }

    /**
     * Public profile: only non-anonymous, publicly visible content. The owner additionally sees
     * bookmarks. Anonymous answers are never linked to the profile (not even counted publicly).
     */
    public function show(Request $request, User $user): View
    {
        abort_if($user->isDeleted(), 404);
        $isOwner = $request->user()?->is($user) ?? false;
        $tab = in_array($request->query('tab'), ['odpovedi', 'otazky', 'oblibene', 'oceneni'], true) ? $request->query('tab') : 'odpovedi';
        if ($tab === 'oblibene' && ! $isOwner) {
            $tab = 'odpovedi';
        }

        $answers = Answer::query()->with(['question', 'user'])->where('user_id', $user->id)
            ->when(! $isOwner, fn ($q) => $q->where('is_anonymous', false)->publiclyVisible()->whereHas('question', fn ($w) => $w->visible()))
            ->latest('published_at');
        $questions = Question::query()->with('category')->where('author_id', $user->id)
            ->when(! $isOwner, fn ($q) => $q->where('is_anonymous', false)->visible())
            ->latest('created_at');

        $publicAnswers = Answer::where('user_id', $user->id)->where('is_anonymous', false)->publiclyVisible();
        $stats = [
            'questions' => Question::where('author_id', $user->id)->where('is_anonymous', false)->visible()->count(),
            'answers' => (clone $publicAnswers)->count(),
            'reactions' => (int) (clone $publicAnswers)->sum('reactions_count'),
        ];

        return view('pages.profiles.show', [
            'profile' => $user->load('location'),
            'isOwner' => $isOwner,
            'tab' => $tab,
            'answers' => $tab === 'odpovedi' ? $answers->paginate(15)->withQueryString() : null,
            'questions' => $tab === 'otazky' ? $questions->paginate(15)->withQueryString() : null,
            'bookmarks' => $tab === 'oblibene' ? Bookmark::where('user_id', $user->id)->with('bookmarkable')->latest('created_at')->paginate(15)->withQueryString() : null,
            'stats' => $stats,
            'region' => $user->preferences()->show_region ? $user->location?->region() : null,
            'achievementsVisible' => $user->preferences()->show_achievements,
            'memberSince' => $user->created_at,
            'streak' => $this->streak($user),
        ]);
    }

    /** Consecutive days (ending today/yesterday) with an answer to the question of the day. */
    private function streak(User $user): int
    {
        $days = DB::table('answers')->join('daily_questions', 'daily_questions.question_id', '=', 'answers.question_id')
            ->where('answers.user_id', $user->id)->whereNull('answers.deleted_at')
            ->orderByDesc('daily_questions.date')->limit(400)->pluck('daily_questions.date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values();
        $streak = 0;
        $expected = now(config('matocenu.display_timezone'))->startOfDay();
        foreach ($days as $i => $d) {
            if ($i === 0 && $d !== $expected->toDateString()) {
                $expected = $expected->subDay();
            }
            if ($d !== $expected->toDateString()) {
                break;
            }
            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }
}
