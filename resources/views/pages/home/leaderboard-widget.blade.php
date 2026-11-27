@php
    $lb = array_slice(app(\App\Domain\Rankings\Services\RankingEngine::class)->get('authors', 'week'), 0, 5);
    $lbUsers = \App\Models\User::with('settings')->whereIn('id', array_column($lb, 'id'))->get()->keyBy('id');
@endphp
@if ($lb)
    <div class="card">
        <div class="flex items-baseline justify-between"><h2 class="font-display text-xl font-bold">Žebříček týdne</h2><a href="{{ route('rankings.index') }}" class="link text-sm">Celý</a></div>
        <ol class="mt-3 space-y-3">
            @foreach ($lb as $a)
                @continue(! $lbUsers->has($a['id']))
                @php $u = $lbUsers[$a['id']]; $t = $u->settings?->show_achievements ? $u->title() : null; @endphp
                <li class="flex items-center gap-3 text-sm"><span class="w-4 font-display text-lg font-extrabold text-terra-ink dark:text-terra">{{ $a['rank'] }}</span><x-avatar :user="$u" :size="34" />
                    <span class="min-w-0 flex-1"><b class="block truncate">{{ $u->name }}</b>@if ($t)<span class="text-[12px] text-muted">{{ $t->name }}</span>@endif</span><b class="tabular-nums">{{ cz_number($a['score'], 1) }}</b></li>
            @endforeach
        </ol>
    </div>
@endif
