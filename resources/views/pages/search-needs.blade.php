@feature('needs')
@php
    $needHits = trim($q) === '' ? collect() : \App\Models\Need::query()->visible()
        ->where(fn ($w) => $w->whereRaw("to_tsvector('simple', mtc_unaccent(title || ' ' || description)) @@ plainto_tsquery('simple', mtc_unaccent(?))", [$q])
            ->orWhereRaw('mtc_unaccent(title) % mtc_unaccent(?)', [$q]))->orderByDesc('interested_count')->limit(10)->get();
@endphp
<section class="card lg:col-span-2"><h2 class="font-display text-xl font-bold">Potřeby</h2>
    <ul class="mt-2 divide-y divide-line">@forelse ($needHits as $n)<li class="flex justify-between gap-3 py-3"><a href="{{ $n->url() }}" class="font-semibold">{{ $n->title }}</a><span class="text-sm text-muted">{{ cz_plural($n->interested_count, 'zájemce', 'zájemci', 'zájemců') }}</span></li>@empty<li class="py-3 text-sm text-muted">Žádná potřeba.</li>@endforelse</ul></section>
@endfeature
