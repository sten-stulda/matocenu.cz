<?php

namespace App\Http\Controllers\Needs;

use App\Domain\Needs\Services\NeedStats;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Need;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NeedController extends Controller
{
    public const SORTS = ['zajem' => 'Nejvíc zájemců', 'platit' => 'Ochotni platit', 'bez-nabidky' => 'Bez nabídky řešení', 'nove' => 'Nejnovější'];

    public function index(Request $request, NeedStats $stats): View
    {
        $sort = array_key_exists($request->query('razeni'), self::SORTS) ? $request->query('razeni') : 'zajem';
        $category = $request->filled('kategorie') ? Category::where('slug', $request->query('kategorie'))->first() : null;
        $region = $request->filled('kraj') ? Location::where('type', 'region')->where('slug', $request->query('kraj'))->first() : null;

        $needs = Need::query()->visible()->with(['category', 'location'])->whereIn('status', ['open', 'in_progress'])
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($region, fn ($q) => $q->where(fn ($w) => $w->whereIn('location_id', $region->descendantIds())->orWhereNull('location_id')))
            ->when($sort === 'zajem', fn ($q) => $q->orderByDesc('interested_count'))
            ->when($sort === 'platit', fn ($q) => $q->where('would_pay_count', '>', 0)->orderByDesc('would_pay_count'))
            ->when($sort === 'bez-nabidky', fn ($q) => $q->where('offers_count', 0)->orderByDesc('interested_count'))
            ->latest('published_at')->paginate(15)->withQueryString();

        // Willingness to pay summaries for the listed needs (k-anonymous).
        $pay = [];
        foreach ($needs as $n) {
            $s = $stats->forNeed($n);
            $pay[$n->id] = $s['amounts_shown'] ? $s : null;
        }

        return view('pages.needs.index', [
            'needs' => $needs, 'sort' => $sort, 'category' => $category, 'region' => $region, 'pay' => $pay,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'regions' => Location::where('type', 'region')->orderBy('name')->get(),
            'demand' => $stats->regionDemand($category?->id),
            'solved' => Need::query()->visible()->where('status', 'solved')->latest('updated_at')->first(),
        ]);
    }

    public function show(Request $request, Need $need, NeedStats $stats): View
    {
        abort_unless($need->isPubliclyVisible() || ($request->user() && ($request->user()->id === $need->user_id || $request->user()->can('needs.manage'))), 404);
        $need->load(['category', 'location', 'user']);

        return view('pages.needs.show', [
            'need' => $need,
            'stats' => $stats->forNeed($need),
            'history' => DB::table('need_status_history')->where('need_id', $need->id)->orderBy('created_at')->get(),
        ]);
    }
}
