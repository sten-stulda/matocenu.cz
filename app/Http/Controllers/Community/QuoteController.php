<?php

namespace App\Http\Controllers\Community;

use App\Domain\Quotes\Services\QuoteSelector;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function index(QuoteSelector $selector): View
    {
        return view('pages.quotes.index', [
            'current' => $selector->current(),
            'archive' => Quote::with(['answer.question', 'answer.user', 'style'])->whereHas('answer', fn ($q) => $q->publiclyVisible())
                ->latest('awarded_at')->paginate(30),
        ]);
    }
}
