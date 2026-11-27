@php $tabs = ['tyden' => 'Týden', 'den' => 'Den', 'mesic' => 'Měsíc'] + (auth()->user()?->location_id ? ['kraj' => 'Můj kraj'] : []); @endphp
<x-layouts::app title="Žebříčky" description="Nejoblíbenější autoři, nejlepší odpovědi, nejzajímavější otázky a nejaktivnější kraje na MáToCenu.cz." current="rankings">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-4xl font-extrabold sm:text-5xl">Žebříčky</h1>
        <nav class="flex gap-1" aria-label="Období">
            @foreach ($tabs as $k => $l)
                <a href="?obdobi={{ $k }}" @class(['btn btn-sm', 'bg-forest text-cream' => $tab === $k, 'text-ink hover:bg-surface-2' => $tab !== $k]) @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_400px]">
        <section class="card">
            <h2 class="font-display text-xl font-bold">Nejoblíbenější autor {{ ['den' => 'dne', 'tyden' => 'týdne', 'mesic' => 'měsíce', 'kraj' => 'týdne v kraji '.($region?->name ?? '')][$tab] }}</h2>
            <ol class="mt-3 divide-y divide-line">
                @forelse ($authors as $a)
                    @php $u = $users[$a['id']]; $title = $u->preferences()->show_achievements ? $u->title() : null; $d = $deltas[$a['id']] ?? null; @endphp
                    <li class="flex items-center gap-3 py-3">
                        <span class="w-7 font-display text-2xl font-extrabold text-terra-ink dark:text-terra">{{ $a['rank'] }}</span>
                        <x-avatar :user="$u" :size="40" />
                        <span class="min-w-0 flex-1 text-sm"><a href="{{ route('profiles.show', $u) }}" class="font-bold text-ink no-underline hover:underline">{{ $u->name }}</a>@if ($title)<span class="text-muted"> · {{ $title->name }}</span>@endif
                            <span class="block text-[12px] text-muted">{{ cz_plural($a['answers'], 'hodnocená odpověď', 'hodnocené odpovědi', 'hodnocených odpovědí') }}@if ($a['top_reaction']) · nejvíc „{{ $a['top_reaction'] }}“@endif</span></span>
                        <span class="font-bold tabular-nums">{{ cz_number($a['score'], 1) }}</span>
                        <span class="w-9 text-right text-[12px] font-bold {{ $d > 0 ? 'text-forest dark:text-primary' : 'text-terra-ink' }}">@if ($d) {{ $d > 0 ? '▲' : '▼' }}{{ abs($d) }}@endif</span>
                    </li>
                @empty
                    <li class="py-4"><x-empty hand="Žebříček je zatím prázdný." title="Body se sbírají za reakce ostatních na tvé odpovědi." /></li>
                @endforelse
            </ol>
            <p class="mt-3 text-[12px] text-muted">Body jen za reakce jiných lidí. Do žebříčku se počítají max. {{ setting('rankings.max_answers_per_day') }} odpovědi autora denně – kvalita, ne kvantita. Anonymní odpovědi se do autorského žebříčku nepočítají. <a href="{{ route('rankings.method') }}" class="link">Jak počítáme</a></p>
        </section>

        <aside class="space-y-4">
            @if ($bestAnswer)
                <a href="{{ $bestAnswer->url() }}" class="block rounded-[10px] bg-mustard p-5 text-charcoal no-underline">
                    <p class="text-[12px] font-bold uppercase tracking-[.12em]">Nejlepší odpověď {{ ['den' => 'dne', 'tyden' => 'týdne', 'mesic' => 'měsíce', 'kraj' => 'z kraje'][$tab] }}</p>
                    <p class="mt-1 font-display text-xl leading-snug font-bold">„{{ $bestAnswer->display_text }}“</p>
                    <p class="mt-2 text-[13px] font-semibold">{{ $bestAnswer->publicAuthor()?->name ?? 'Anonym' }}@if ($bestAnswer->style) · {{ $bestAnswer->style->name }}@endif · {{ cz_plural($bestAnswer->reactions_count, 'reakce', 'reakce', 'reakcí') }}</p>
                </a>
            @endif
            @if ($interesting)
                <a href="{{ $interesting->url() }}" class="card block no-underline">
                    <p class="eyebrow">Nejzajímavější otázka</p>
                    <p class="mt-1 font-display text-lg font-bold text-ink">{{ $interesting->text }}</p>
                    <p class="mt-1 text-[13px] text-muted">{{ cz_plural($interestingStats['answers'], 'odpověď', 'odpovědi', 'odpovědí') }} · {{ cz_plural($interestingStats['comments'], 'komentář', 'komentáře', 'komentářů') }} · {{ $interestingStats['reactors'] }} reagujících</p>
                </a>
            @endif
            @if ($regions)
                <div class="rounded-[10px] bg-forest p-5 text-cream">
                    <p class="text-[12px] font-bold uppercase tracking-[.12em] text-mustard">Nejaktivnější region</p>
                    <ol class="mt-2 space-y-1.5">@foreach ($regions as $r)<li class="flex justify-between"><span @class(['font-bold' => $r['rank'] === 1])>{{ $r['rank'] }}. {{ $r['name'] }}</span><span class="tabular-nums">{{ cz_number($r['score']) }}</span></li>@endforeach</ol>
                </div>
            @endif
            @includeIf('pages.rankings.partials.needs-card')
            @feature('quotes')<a href="{{ route('quotes.index') }}" class="btn-outline w-full">Hláška dne, týdne a měsíce</a>@endfeature
        </aside>
    </div>

    @if ($answers->isNotEmpty())
        @php $reactionData = app(\App\Domain\Reactions\Services\ReactionSummary::class)->for('answer', $answers, auth()->user()); @endphp
        <h2 class="h-section mt-10">Nejlépe hodnocené odpovědi</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@foreach ($answers as $a)<x-answer-card :answer="$a" :show-question="true" :reaction-data="$reactionData[$a->id] ?? null" />@endforeach</div>
    @endif
</x-layouts::app>
