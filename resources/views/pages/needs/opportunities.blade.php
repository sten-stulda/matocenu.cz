@php $maxDemand = max(1, collect($demand)->max('count') ?? 1); @endphp
<x-layouts::app title="Příležitosti" description="Anonymizované přehledy skutečné poptávky pro podnikatele a obce.">
    <p class="eyebrow">Má to cenu uskutečnit?</p>
    <h1 class="font-display text-4xl font-extrabold sm:text-5xl">Příležitosti z reálné poptávky</h1>
    <p class="mt-2 max-w-3xl text-muted">Agregovaná a anonymizovaná data z potřeb, ke kterým se lidé přidali. Deklarovaná ochota platit je nezávazná a není to odhad garantovaných tržeb. Osobní údaje neprodáváme ani nezveřejňujeme.</p>
    <form method="GET" class="mt-5 flex flex-wrap gap-2">
        <select name="kategorie" class="input w-auto" onchange="this.form.submit()" aria-label="Kategorie"><option value="">Všechny kategorie</option>@foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($category?->id === $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="kraj" class="input w-auto" onchange="this.form.submit()" aria-label="Kraj"><option value="">Celá ČR</option>@foreach ($regions as $r)<option value="{{ $r->slug }}" @selected($region?->id === $r->id)>{{ $r->name }}</option>@endforeach</select>
    </form>
    @auth<div class="mt-4"><livewire:opportunity-follow :category-id="$category?->id" :region-id="$region?->id" /></div>@endauth

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <section class="card overflow-x-auto p-0">
            <table class="table-admin">
                <thead><tr><th>Potřeba</th><th>Kde</th><th class="text-right">Zájemci</th><th class="text-right">Zaplatili by</th><th>Ochota platit (medián)</th><th class="text-right">Odhad / měs.</th><th class="text-right">Nabídky</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    @php $n = $r['need']; $s = $r['s']; @endphp
                    <tr><td class="max-w-xs"><a href="{{ $n->url() }}" class="font-semibold">{{ $n->title }}</a><br><span class="text-xs text-muted">{{ $n->category->name }}</span></td>
                        <td class="text-sm">{{ $n->location?->name ?? 'Celá ČR' }}</td>
                        <td class="text-right font-bold">{{ $n->interested_count }}@if ($s['growth']['recent'] > $s['growth']['previous'])<br><span class="text-xs text-forest">▲ +{{ $s['growth']['recent'] }} za 14 dní</span>@endif</td>
                        <td class="text-right">{{ $s['payers'] }}@if ($s['commitments'])<br><span class="text-xs text-muted">{{ $s['commitments'] }} závazně</span>@endif</td>
                        <td class="text-sm">@if ($s['amounts_shown']){{ cz_number($s['median']) }} Kč{{ $s['frequency'] ? ' '.$s['frequency']->short() : '' }}<br><span class="text-xs text-muted">{{ cz_number($s['p25']) }}–{{ cz_number($s['p75']) }} Kč</span>@else<span class="text-xs text-muted">málo dat</span>@endif</td>
                        <td class="text-right text-sm">{{ $s['monthly_estimate'] ? cz_number($s['monthly_estimate']).' Kč' : '–' }}</td>
                        <td class="text-right">{{ $n->offers_count }}</td></tr>
                @empty
                    <tr><td colspan="7"><x-empty title="Pro tento výběr zatím nejsou data." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
        <aside class="space-y-4">
            <div class="card"><h2 class="font-display text-xl font-bold">Poptávka podle kraje</h2>
                @if ($demand)<ul class="mt-3 space-y-2 text-sm">@foreach ($demand as $d)<li class="grid grid-cols-[130px_1fr_40px] items-center gap-2"><span class="truncate">{{ $d['name'] }}</span><span class="h-2 rounded bg-surface-2"><span class="block h-2 rounded bg-terra" style="width: {{ round($d['count'] / $maxDemand * 100) }}%"></span></span><b class="text-right">{{ $d['count'] }}</b></li>@endforeach</ul>
                @else<p class="mt-2 text-sm text-muted">Zatím málo dat (kraje ukazujeme od {{ \App\Domain\Needs\Services\NeedStats::K_REGION }} lidí).</p>@endif</div>
            <div class="card"><h2 class="font-display text-xl font-bold">Rostoucí zájem</h2>
                <ul class="mt-2">@forelse ($growing as $g)<li class="flex justify-between gap-2 border-b border-line py-2 text-sm last:border-0"><a href="{{ $g['need']->url() }}">{{ $g['need']->title }}</a><b class="text-forest">+{{ $g['s']['growth']['recent'] }}</b></li>@empty<li class="py-2 text-sm text-muted">Zatím nic neroste.</li>@endforelse</ul></div>
            <p class="text-[12px] text-muted">Odhad měsíční poptávky = počet plátců × medián × frekvence (jen u týdenních, měsíčních a ročních plateb). Je to orientační, nezávazné číslo.</p>
        </aside>
    </div>
</x-layouts::app>
