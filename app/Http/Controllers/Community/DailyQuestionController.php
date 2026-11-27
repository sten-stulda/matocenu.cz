<?php

namespace App\Http\Controllers\Community;

use App\Domain\Questions\Services\DailyQuestions;
use App\Http\Controllers\Controller;
use App\Models\DailyQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DailyQuestionController extends Controller
{
    public function show(DailyQuestions $daily): RedirectResponse
    {
        $entry = $daily->current();

        return $entry ? redirect()->route('questions.show', $entry->question) : redirect()->route('home');
    }

    public function archive(): View
    {
        $entries = DailyQuestion::with('question.category')->where('status', 'published')
            ->whereHas('question', fn ($q) => $q->visible())->orderByDesc('date')->paginate(30);

        return view('pages.daily.archive', ['entries' => $entries]);
    }
}
