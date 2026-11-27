<div class="card {{ $r['today'] >= $r['daily_budget'] ? 'bg-terra-100 dark:bg-surface' : '' }}">
    <p class="text-sm text-muted">Náklady AI dnes</p>
    <p class="mt-1 font-display text-4xl font-extrabold">{{ cz_number($r['today'], 2) }} Kč</p>
    <div class="mt-2 h-2 rounded bg-surface-2"><div class="h-2 rounded bg-mustard" style="width: {{ min(100, $r['daily_budget'] > 0 ? $r['today'] / $r['daily_budget'] * 100 : 0) }}%"></div></div>
    <p class="mt-1 text-sm text-muted">limit {{ cz_number($r['daily_budget'], 0) }} Kč@if ($r['per_generation'] !== null) · {{ cz_number($r['per_generation'], 3) }} Kč / generování @endif @unless ($aiOn) · <b class="text-terra-ink">AI není nastavena</b>@endunless</p>
    <a href="{{ route('admin.ai.costs') }}" class="link mt-2 inline-block text-sm">Detail nákladů</a>
</div>
<div class="card">
    <h2 class="font-display text-xl font-bold">Nejpopulárnější styly (30 dní)</h2>
    <ul class="mt-3 space-y-1.5 text-sm">
        @forelse ($styles as $s)<li class="flex justify-between"><span>{{ $s->name }}</span><b>{{ $s->n }}</b></li>
        @empty<li class="text-muted">Zatím žádné stylizované odpovědi ({{ $stylesTotal }} aktivních stylů).</li>@endforelse
    </ul>
</div>
@include('pages.admin.widgets.ai-chart', ['days' => $r['days']])
