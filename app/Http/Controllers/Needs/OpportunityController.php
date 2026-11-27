<?php

namespace App\Http\Controllers\Needs;

use App\Domain\Needs\Services\NeedStats;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Need;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Má to cenu uskutečnit?" – aggregated, anonymised demand for entrepreneurs and municipalities. */
class OpportunityController extends Controller
{
    public function __invoke(Request $request, NeedStats $stats): View
    {
        $category = $request->filled('kategorie') ? Category::where('slug', $request->query('kategorie'))->first() : null;
        $region = $request->filled('kraj') ? Location::where('type', 'region')->where('slug', $request->query('kraj'))->first() : null;
        $needs = Need::query()->visible()->with(['category', 'location'])->whereIn('status', ['open', 'in_progress'])->where('interested_count', '>', 0)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($region, fn ($q) => $q->whereIn('location_id', $region->descendantIds()))
            ->orderByDesc('interested_count')->limit(30)->get();
        $rows = $needs->map(fn ($n) => ['need' => $n, 's' => $stats->forNeed($n)]);

        return view('pages.needs.opportunities', [
            'rows' => $rows,
            'growing' => $rows->filter(fn ($r) => $r['s']['growth']['recent'] > $r['s']['growth']['previous'])->sortByDesc(fn ($r) => $r['s']['growth']['recent'] - $r['s']['growth']['previous'])->take(5),
            'demand' => $stats->regionDemand($category?->id),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->get(),
            'category' => $category, 'region' => $region,
        ]);
    }
}
