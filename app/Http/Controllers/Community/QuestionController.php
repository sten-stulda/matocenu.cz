<?php

namespace App\Http\Controllers\Community;

use App\Domain\Questions\Services\AnswerListing;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public const SORTS = ['nove' => 'Nejnovější', 'diskutovane' => 'Právě diskutované', 'popularni' => 'Nejpopulárnější', 'bez-odpovedi' => 'Bez odpovědi'];

    public function index(Request $request): View
    {
        $sort = array_key_exists($request->query('razeni'), self::SORTS) ? $request->query('razeni') : 'nove';
        $category = $request->filled('kategorie') ? Category::where('slug', $request->query('kategorie'))->first() : null;
        $region = $request->filled('kraj') ? Location::where('type', 'region')->where('slug', $request->query('kraj'))->first() : null;

        $questions = Question::query()->visible()->with(['category', 'location'])
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($region, fn ($q) => $q->whereIn('location_id', $region->descendantIds()))
            ->when($sort === 'nove', fn ($q) => $q->latest('published_at'))
            ->when($sort === 'diskutovane', fn ($q) => $q->orderByDesc('last_activity_at'))
            ->when($sort === 'popularni', fn ($q) => $q->orderByDesc('answers_count')->latest('published_at'))
            ->when($sort === 'bez-odpovedi', fn ($q) => $q->where('answers_count', 0)->live()->latest('published_at'))
            ->paginate(20)->withQueryString();

        return view('pages.questions.index', [
            'questions' => $questions,
            'sort' => $sort,
            'category' => $category,
            'region' => $region,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Question $question, AnswerListing $listing): View
    {
        $this->authorize('view', $question);
        $question->load(['category', 'location', 'author', 'options']);
        $viewer = $request->user();
        $sort = in_array($request->query('razeni'), ['nejlepsi', 'nove', 'kraj'], true) ? $request->query('razeni') : 'nejlepsi';

        return view('pages.questions.show', [
            'question' => $question,
            'answers' => $listing->forQuestion($question, $viewer, $sort),
            'sort' => $sort,
            'myAnswer' => $viewer ? $question->answers()->where('user_id', $viewer->id)->first() : null,
            'isDaily' => $question->dailyEntries()->where('status', 'published')->exists(),
        ]);
    }

    public function random(): RedirectResponse
    {
        $q = Question::query()->visible()->inRandomOrder()->first();

        return $q ? redirect()->route('questions.show', $q) : redirect()->route('questions.index')->with('status', 'Zatím tu žádné otázky nejsou.');
    }
}
