<?php

namespace App\Http\Controllers\Community;

use App\Domain\Questions\Services\HomepageData;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnswerController extends Controller
{
    public function show(Answer $answer): View
    {
        $answer->load(['question.category', 'user', 'style']);
        $this->authorize('view', $answer);

        return view('pages.answers.show', ['answer' => $answer]);
    }

    public function best(Request $request, HomepageData $data): View
    {
        $period = in_array($request->query('obdobi'), ['den', 'tyden', 'mesic'], true) ? $request->query('obdobi') : 'tyden';
        $since = match ($period) {
            'den' => now()->subDay(),
            'mesic' => now()->subDays(30),
            default => now()->subDays(7),
        };

        return view('pages.answers.best', ['answers' => $data->bestAnswers($since, 30), 'period' => $period]);
    }
}
