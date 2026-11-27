<?php

namespace App\Http\Controllers\Community;

use App\Domain\Rankings\Services\Periods;
use App\Domain\Rankings\Services\RankingEngine;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function index(Request $request, RankingEngine $engine): View
    {
        $map = ['den' => 'day', 'tyden' => 'week', 'mesic' => 'month'];
        $tab = array_key_exists($request->query('obdobi'), $map) || $request->query('obdobi') === 'kraj' ? $request->query('obdobi') : 'tyden';
        $period = $map[$tab] ?? 'week';
        $region = $tab === 'kraj' ? $request->user()?->location?->region() : null;

        if ($region) {
            // Regional view: computed live for the viewer's region (week).
            [$from, $to] = Periods::current('week');
            $regionUserIds = User::whereIn('location_id', $region->descendantIds())->pluck('id')->all();
            $authors = array_values(array_filter($engine->authors($from, $to, 200), fn ($a) => in_array($a['id'], $regionUserIds, true)));
            $answers = array_values(array_filter($engine->answers($from, $to, 200), fn ($a) => ! $a['anonymous'] && in_array($a['user_id'], $regionUserIds, true)));
        } else {
            $authors = $engine->get('authors', $period);
            $answers = $engine->get('answers', $period);
        }

        $authorItems = array_slice($authors, 0, 10);
        $users = User::with('settings')->whereIn('id', array_column($authorItems, 'id'))->get()->keyBy('id');
        $bestAnswer = ($first = collect($answers)->first(fn ($a) => ! $a['suspicious'])) ? Answer::with(['user', 'question', 'style'])->find($first['id']) : null;
        $interesting = ($q = collect($engine->get('questions_interesting', $period))->first()) ? Question::find($q['id']) : null;

        return view('pages.rankings.index', [
            'tab' => $tab,
            'period' => $period,
            'region' => $region,
            'authors' => collect($authorItems)->filter(fn ($a) => $users->has($a['id']) && ! $users[$a['id']]->isDeleted())->values(),
            'users' => $users,
            'deltas' => $region ? [] : $engine->deltas('authors', $period, $authorItems),
            'bestAnswer' => $bestAnswer,
            'bestAnswerScore' => $first ?? null,
            'interesting' => $interesting,
            'interestingStats' => $q ?? null,
            'regions' => array_slice($engine->get('regions', $period), 0, 5),
            'answers' => Answer::with(['user', 'question', 'style'])->whereIn('id', array_column(array_slice($answers, 0, 9), 'id'))->get()->sortBy(fn ($a) => array_search($a->id, array_column($answers, 'id')))->values(),
        ]);
    }
}
