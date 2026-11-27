<?php

namespace App\Http\Controllers\Community;

use App\Domain\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\LanguageStyle;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): View
    {
        $q = mb_substr((string) $request->query('q', ''), 0, 120);
        $region = $request->filled('kraj') ? Location::where('type', 'region')->where('slug', $request->query('kraj'))->first() : null;
        $category = $request->filled('kategorie') ? Category::where('slug', $request->query('kategorie'))->first() : null;
        $style = $request->filled('styl') ? LanguageStyle::where('slug', $request->query('styl'))->first() : null;

        return view('pages.search', [
            'q' => $q,
            'results' => $search->search($q, ['category' => $category?->id, 'region' => $region?->descendantIds(), 'style' => $style?->id]),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->get(),
            'styles' => LanguageStyle::active()->filter->isGeneratable(),
            'filters' => ['kategorie' => $category?->slug, 'kraj' => $region?->slug, 'styl' => $style?->slug],
        ]);
    }
}
