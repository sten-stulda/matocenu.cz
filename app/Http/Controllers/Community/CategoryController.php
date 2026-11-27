<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Question;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::where('is_active', true)->orderBy('sort_order')
            ->withCount(['questions as visible_count' => fn ($q) => $q->visible()])
            ->withCount(['questions as recent_count' => fn ($q) => $q->visible()->where('published_at', '>=', now()->subDays(7))])
            ->get();

        return view('pages.categories.index', ['categories' => $categories]);
    }

    public function show(Category $category): View
    {
        abort_unless($category->is_active, 404);
        $questions = Question::query()->visible()->with('category')->where('category_id', $category->id)->latest('published_at')->paginate(20);

        return view('pages.categories.show', ['category' => $category, 'questions' => $questions]);
    }
}
