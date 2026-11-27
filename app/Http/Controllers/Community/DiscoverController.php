<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Question;
use Illuminate\View\View;

class DiscoverController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.discover', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->withCount(['questions as visible_count' => fn ($q) => $q->visible()])->get(),
            'random' => Question::query()->visible()->with('category')->inRandomOrder()->first(),
            'unanswered' => Question::query()->live()->with('category')->where('answers_count', 0)->latest('published_at')->limit(5)->get(),
            'popular' => Question::query()->visible()->with('category')->where('answers_count', '>', 0)->orderByDesc('answers_count')->limit(5)->get(),
        ]);
    }
}
