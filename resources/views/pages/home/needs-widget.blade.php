@feature('needs')
@php $topNeeds = app(\App\Domain\Needs\Services\NeedStats::class)->top(5); $sum = max(1, $topNeeds->sum('interested_count')); $bars = ['bg-forest dark:bg-primary', 'bg-mustard', 'bg-terra', 'bg-forest-300', 'bg-terra-ink']; @endphp
<section class="card">
    <h2 class="font-display text-xl font-bold">Co lidem nejvíce chybí</h2>
    @if ($topNeeds->isNotEmpty())
        <p class="text-[12px] text-muted">Podíl zájemců mezi nejsilnějšími potřebami</p>
        <ul class="mt-3 space-y-3">
            @foreach ($topNeeds as $i => $n)
                <li><div class="flex justify-between text-sm"><a href="{{ $n->url() }}" class="font-semibold text-ink no-underline hover:underline">{{ \Illuminate\Support\Str::limit($n->title, 40) }}</a><span>{{ round($n->interested_count / $sum * 100) }} %</span></div>
                    <div class="mt-1 h-2 rounded bg-surface-2"><div class="h-2 rounded {{ $bars[$i % 5] }}" style="width: {{ round($n->interested_count / $sum * 100) }}%"></div></div></li>
            @endforeach
        </ul>
    @else
        <p class="mt-3 text-sm text-muted">Zatím tu nikdo nenapsal, co mu chybí. <a href="{{ route('needs.create') }}" class="link">Buď první</a>.</p>
    @endif
</section>
@endfeature
