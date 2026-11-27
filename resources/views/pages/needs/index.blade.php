@php $maxDemand = max(1, collect($demand)->max('count') ?? 1); @endphp
<x-layouts::app title="Co lidem chybí" description="Skutečné potřeby lidí, sběr zájmu a ochoty zaplatit. Z nejsilnějších potřeb vznikají podnikatelské příležitosti." current="needs">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-3xl">
            <p class="eyebrow">Co lidem chybí</p>
            <h1 class="font-display text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-[52px]">Skutečné potřeby. Skuteční zájemci.</h1>
            <p class="mt-3 text-muted">Tady se nevtipkuje – humor je vypnutý. Napiš, co ti chybí, ostatní se přidají. Z nejsilnějších potřeb vznikají podnikatelské příležitosti.</p>
        </div>
        <a href="{{ route('needs.create') }}" class="btn-forest">Přidat potřebu</a>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2">
        <select name="kategorie" class="input w-auto" aria-label="Kategorie" onchange="this.form.submit()"><option value="">Kategorie: Všechny</option>@foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($category?->id === $c->id)>{{ $c->name }}</option>@endforeach</select>
        <select name="kraj" class="input w-auto" aria-label="Lokalita" onchange="this.form.submit()"><option value="">Lokalita: Celá ČR</option>@foreach ($regions as $r)<option value="{{ $r->slug }}" @selected($region?->id === $r->id)>{{ $r->name }}</option>@endforeach</select>
        <input type="hidden" name="razeni" value="{{ $sort }}">
        @foreach (\App\Http\Controllers\Needs\NeedController::SORTS as $k => $l)
            <a href="{{ request()->fullUrlWithQuery(['razeni' => $k, 'page' => null]) }}" @class(['btn btn-sm', 'bg-forest text-cream' => $sort === $k, 'text-ink hover:bg-surface-2' => $sort !== $k])>{{ $l }}</a>
        @endforeach
    </form>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-4">
            @forelse ($needs as $n)
                @include('pages.needs.partials.card', ['need' => $n, 'pay' => $pay[$n->id] ?? null])
            @empty
                <x-empty hand="Tady zatím nic nechybí…" title="…nebo to ještě nikdo nenapsal. Buď první.">
                    <a href="{{ route('needs.create') }}" class="link">Přidat potřebu</a>
                </x-empty>
            @endforelse
            {{ $needs->links() }}
        </div>
        <aside class="space-y-4">
            <div class="card">
                <h2 class="font-display text-xl font-bold">Poptávka podle kraje</h2>
                @if ($demand)
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($demand as $d)
                            <li class="grid grid-cols-[130px_1fr_40px] items-center gap-2"><span class="truncate">{{ $d['name'] }}</span>
                                <span class="h-2 rounded bg-surface-2"><span class="block h-2 rounded bg-terra" style="width: {{ round($d['count'] / $maxDemand * 100) }}%"></span></span><b class="text-right tabular-nums">{{ $d['count'] }}</b></li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-muted">Kraje ukážeme, až v nich bude aspoň {{ \App\Domain\Needs\Services\NeedStats::K_REGION }} zájemců (ochrana soukromí).</p>
                @endif
            </div>
            @feature('opportunities')
                <div class="rounded-[10px] bg-forest p-6 text-cream">
                    <p class="text-[12px] font-bold uppercase tracking-[.12em] text-mustard">Pro podnikatele a obce</p>
                    <h2 class="mt-1 font-display text-2xl font-bold">Hledáte, co lidé opravdu chtějí?</h2>
                    <p class="mt-2 text-sm text-forest-100">Sledujte kategorii nebo region a dostávejte souhrn nových potřeb. Anonymizovaná data, žádné kontakty bez souhlasu.</p>
                    <a href="{{ route('opportunities.index') }}" class="btn-accent mt-4">Sledovat příležitosti</a>
                </div>
            @endfeature
            @if ($solved)
                <div class="card-dashed">
                    <p class="font-hand text-2xl text-forest dark:text-primary">Splněno!</p>
                    <p class="mt-1 text-sm">„{{ $solved->title }}“ – {{ cz_plural($solved->interested_count, 'zájemce', 'zájemci', 'zájemců') }}.@if ($solved->solved_note) {{ $solved->solved_note }}@endif</p>
                </div>
            @endif
        </aside>
    </div>
</x-layouts::app>
