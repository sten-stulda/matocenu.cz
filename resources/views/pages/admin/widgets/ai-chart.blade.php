@php $max = max(0.01, collect($days)->max('czk')); @endphp
<section class="card">
    <h2 class="font-display text-xl font-bold">Náklady AI – posledních 7 dní</h2>
    <div class="mt-4 flex h-48 items-end gap-2" role="img" aria-label="Sloupcový graf nákladů AI za posledních 7 dní: {{ collect($days)->map(fn ($d) => $d['label'].' '.cz_number($d['czk'], 2).' Kč')->implode(', ') }}">
        @foreach ($days as $d)
            <div class="flex flex-1 flex-col items-center justify-end gap-1">
                <span class="text-[11px] font-semibold tabular-nums">{{ cz_number($d['czk'], $d['czk'] < 10 ? 2 : 0) }}</span>
                <div class="w-full rounded-t {{ $d['today'] ? 'bg-mustard' : 'bg-forest dark:bg-primary' }}" style="height: {{ max(2, round($d['czk'] / $max * 140)) }}px"></div>
                <span class="text-[12px] text-muted">{{ $d['label'] }}</span>
            </div>
        @endforeach
    </div>
    <p class="mt-3 text-xs text-muted">Kč za den · při překročení limitu se stylizace dočasně vypne a uživatelé zveřejní originál.</p>
</section>
