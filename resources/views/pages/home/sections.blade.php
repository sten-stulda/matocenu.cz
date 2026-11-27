@php
    $answersAll = collect([$dailyTop ?? collect(), $dailyNewest ?? collect(), $bestWeek ?? collect()])->flatten();
    $reactionData = app(\App\Domain\Reactions\Services\ReactionSummary::class)->for('answer', $answersAll->unique('id'), auth()->user());
@endphp

@if ($dailyQuestion)
    <section class="mt-10" aria-labelledby="best-h">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="best-h" class="h-section">Nejlépe hodnocené odpovědi</h2>
            <a href="{{ $dailyQuestion->url() }}" class="link text-sm">Všechny odpovědi</a>
        </div>
        @if ($dailyTop->isNotEmpty())
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($dailyTop as $a)<x-answer-card :answer="$a" :reaction-data="$reactionData[$a->id] ?? null" />@endforeach
            </div>
        @elseif ($dailyNewest->isNotEmpty())
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($dailyNewest as $a)<x-answer-card :answer="$a" :reaction-data="$reactionData[$a->id] ?? null" />@endforeach
            </div>
        @else
            <x-empty class="mt-4" hand="Zatím ticho po pěšině…" title="Buď první, kdo odpoví na dnešní otázku." />
        @endif
        @if ($dailyTop->isNotEmpty() && $dailyNewest->isNotEmpty())
            <h3 class="mt-8 font-display text-xl font-bold">Nejnovější odpovědi</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                @foreach ($dailyNewest as $a)<x-answer-card :answer="$a" :reaction-data="$reactionData[$a->id] ?? null" />@endforeach
            </div>
        @endif
    </section>
@endif

<div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
    @includeIf('pages.home.needs-widget')

    <section class="card">
        <h2 class="font-display text-xl font-bold">Právě diskutované</h2>
        <ul class="mt-2">@forelse ($discussed as $q)<x-question-row :question="$q" />@empty<li class="py-3 text-sm text-muted">Zatím je tu klid.</li>@endforelse</ul>
    </section>

    <section class="card">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="font-display text-xl font-bold">Z mého okolí</h2>
            @if ($viewerRegion)<a href="{{ route('account.settings') }}" class="text-[12px] font-semibold">{{ $viewerRegion->name }} · změnit</a>@endif
        </div>
        @if ($local === null)
            <p class="mt-3 text-sm text-muted">@auth Nastav si kraj nebo okres v <a class="link" href="{{ route('account.settings') }}">nastavení</a> a uvidíš otázky z okolí. @else <a class="link" href="{{ route('login') }}">Přihlas se</a> a nastav si kraj – uvidíš otázky z okolí. @endauth</p>
        @else
            <ul class="mt-2">@forelse ($local as $q)<x-question-row :question="$q" />@empty<li class="py-3 text-sm text-muted">Z tvého kraje zatím nikdo nic nepoložil. <a class="link" href="{{ route('questions.create') }}">Buď první</a>.</li>@endforelse</ul>
        @endif
    </section>

    <section class="card">
        <h2 class="font-display text-xl font-bold">Nejpopulárnější otázky</h2>
        <ul class="mt-2">@forelse ($popular as $q)<x-question-row :question="$q" />@empty<li class="py-3 text-sm text-muted">Zatím bez odpovědí.</li>@endforelse</ul>
    </section>

    <section class="card">
        <h2 class="font-display text-xl font-bold">Nejnovější otázky</h2>
        <ul class="mt-2">@forelse ($latest as $q)<x-question-row :question="$q" meta="time" />@empty<li class="py-3 text-sm text-muted">Zatím žádné otázky.</li>@endforelse</ul>
    </section>

    <section class="flex flex-col rounded-[10px] bg-mustard-100 p-6 text-charcoal dark:bg-surface dark:text-ink">
        <h2 class="font-display text-xl font-bold">Náhodná otázka</h2>
        @if ($random)
            <p class="my-6 flex-1 font-display text-2xl leading-tight font-bold">„{{ $random->text }}“</p>
            <div class="flex flex-wrap gap-2"><a href="{{ $random->url() }}" class="btn-forest btn-sm">Odpovědět</a><a href="{{ route('questions.random') }}" class="btn-outline btn-sm"><x-icon name="dice" :size="16" /> Hodit kostkou znovu</a></div>
        @else
            <p class="mt-3 text-sm">Zatím tu žádné otázky nejsou.</p>
        @endif
    </section>

    <section class="card-dashed">
        <h2 class="font-display text-xl font-bold">Otázky bez odpovědi</h2>
        <p class="font-hand text-lg text-terra-ink dark:text-mustard">buď první – první odpověď má bonus</p>
        <ul class="mt-2">@forelse ($unanswered as $q)<x-question-row :question="$q" meta="answer" />@empty<li class="py-3 text-sm text-muted">Každá otázka už má aspoň jednu odpověď.</li>@endforelse</ul>
    </section>

    @if ($bestWeek->isNotEmpty())
        <section class="card md:col-span-2 xl:col-span-3">
            <div class="flex items-baseline justify-between"><h2 class="font-display text-xl font-bold">Nejlepší odpovědi týdne</h2><a href="{{ route('answers.best') }}" class="link text-sm">Všechny</a></div>
            <div class="mt-4 grid gap-4 md:grid-cols-3">@foreach ($bestWeek as $a)<x-answer-card :answer="$a" :show-question="true" :reaction-data="$reactionData[$a->id] ?? null" />@endforeach</div>
        </section>
    @endif

    <section class="card">
        <div class="flex items-baseline justify-between"><h2 class="font-display text-xl font-bold">Archiv otázek dne</h2><a href="{{ route('daily.archive') }}" class="link text-sm">Celý archiv</a></div>
        <ul class="mt-2">@forelse ($archive as $e)<li class="flex gap-3 border-b border-line py-3 last:border-0"><span class="w-14 shrink-0 text-sm font-bold">{{ $e->date->format('j. n.') }}</span><a class="font-semibold text-ink no-underline hover:underline" href="{{ $e->question->url() }}">{{ $e->question->text }}</a></li>@empty<li class="py-3 text-sm text-muted">Archiv se teprve plní.</li>@endforelse</ul>
    </section>
</div>
