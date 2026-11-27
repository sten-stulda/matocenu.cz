<?php

namespace App\Http\Controllers\Community;

use App\Domain\Sharing\Services\ShareCard;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CardController extends Controller
{
    public function answer(Answer $answer, ShareCard $cards): BinaryFileResponse
    {
        $answer->load(['question', 'user', 'style']);
        abort_unless($answer->isPubliclyVisible() && $answer->question->isVisible(), 404);

        return response()->file($cards->forAnswer($answer), ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function question(Question $question, ShareCard $cards): BinaryFileResponse
    {
        abort_unless($question->isVisible(), 404);

        return response()->file($cards->forQuestion($question), ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }
}
