@php
    $reactionData = app(\App\Domain\Reactions\Services\ReactionSummary::class)->for('answer', $answers, auth()->user());
    $author = $question->is_anonymous || ! $question->author || $question->author->isDeleted() ? null : $question->author;
    $jsonLd = [[
        '@context' => 'https://schema.org', '@type' => 'QAPage',
        'mainEntity' => array_filter([
            '@type' => 'Question', 'name' => $question->text, 'text' => $question->text,
            'answerCount' => $question->answers_count, 'datePublished' => $question->published_at?->toIso8601String(),
            'author' => ['@type' => 'Person', 'name' => $author?->name ?? 'Anonym'],
            'suggestedAnswer' => $answers->take(3)->map(fn ($a) => ['@type' => 'Answer', 'text' => $a->display_text, 'upvoteCount' => $a->reactions_count, 'datePublished' => $a->published_at?->toIso8601String(), 'url' => $a->url(), 'author' => ['@type' => 'Person', 'name' => $a->publicAuthor()?->name ?? 'Anonym']])->values()->all(),
        ]),
    ]];
@endphp
<x-layouts::app :title="$question->text" :description="'Odpovědi skutečných lidí na otázku: '.$question->text" :og-type="'article'" :og-image="Route::has('cards.question') ? route('cards.question', $question) : null" :json-ld="$question->isVisible() ? $jsonLd : []" :noindex="! $question->isVisible()">
    <div class="grid gap-8 lg:grid-cols-[1fr_340px]">
        <div class="min-w-0 space-y-6">
            <nav aria-label="Drobečková navigace" class="text-[13px] text-muted">
                <a href="{{ route('home') }}">Domů</a> / <a href="{{ route('categories.show', $question->category) }}">{{ $question->category->name }}</a> / Detail otázky
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <x-category-badge :category="$question->category" />
                @if ($isDaily)<span class="badge-forest">Otázka dne</span>@endif
                @if ($question->is_sensitive)<span class="badge-terra">Citlivé téma · humor je volitelný</span>@endif
                @if ($question->location)<span class="badge-outline">{{ $question->location->name }}</span>@endif
                @if (! $question->isVisible())<span class="badge-dashed">{{ $question->moderation_status->label() }} – vidíš jen ty a moderátoři</span>@endif
            </div>
            <h1 class="font-display text-[34px] leading-[1.05] font-extrabold tracking-tight sm:text-[52px]">{{ $question->text }}</h1>
            <p class="text-sm text-muted">
                Položil{{ $author ? '/a' : 'a' }} <b class="text-ink">{{ $author?->name ?? ($question->author_id ? 'Anonym' : 'redakce') }}</b>
                · {{ local_time($question->published_at)?->diffForHumans() }} · {{ cz_plural($question->answers_count, 'odpověď', 'odpovědi', 'odpovědí') }}
                @if ($question->followers_count) · {{ cz_plural($question->followers_count, 'člověk sleduje', 'lidé sledují', 'lidí sleduje') }}@endif
            </p>
            <livewire:question-actions :question-id="$question->id" />

            @includeIf('pages.questions.partials.style-switcher')

            <livewire:answer-composer :question-id="$question->id" />

            <section aria-labelledby="odpovedi-h" class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="odpovedi-h" class="h-section">Odpovědi</h2>
                    <nav class="flex gap-1 text-sm" aria-label="Řazení odpovědí">
                        @foreach (['nejlepsi' => 'Nejvíc „To má cenu!“', 'nove' => 'Nejnovější', 'kraj' => 'Z mého kraje'] as $k => $l)
                            @if ($k !== 'kraj' || auth()->user()?->location_id)
                                <a href="?razeni={{ $k }}#odpovedi-h" @class(['border-b-[3px] px-2 py-1.5 font-semibold no-underline', 'border-forest text-ink dark:border-primary' => $sort === $k, 'border-transparent text-muted' => $sort !== $k])>{{ $l }}</a>
                            @endif
                        @endforeach
                    </nav>
                </div>
                @if ($answers->isEmpty())
                    <x-empty hand="Zatím ticho po pěšině…" title="Buď první, kdo odpoví." />
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach ($answers as $a)
                            <x-answer-card :answer="$a" :reaction-data="$reactionData[$a->id] ?? null" wire:key="a{{ $a->id }}" />
                        @endforeach
                    </div>
                    {{ $answers->links() }}
                @endif
            </section>

            <div class="card"><livewire:comments type="question" :id="$question->id" /></div>
        </div>

        <aside class="space-y-6">
            <div class="card">
                <h2 class="font-display text-lg font-bold">Jak to funguje</h2>
                <p class="mt-2 text-sm">AI přepíše jen <b>formu</b>. Význam, fakta a tón zůstávají tvoje. Nic se nezveřejní bez tvého potvrzení a u každé stylizace je vidět štítek „Stylizováno AI“.</p>
                <a href="{{ route('legal.show', 'jak-pracuje-ai') }}" class="link mt-2 inline-block text-sm">Víc o AI</a>
            </div>
            @if ($question->versions()->count() > 1)
                <div class="card text-sm">
                    <h2 class="font-display text-lg font-bold">Historie znění</h2>
                    <ul class="mt-2 space-y-1">@foreach ($question->versions()->limit(5)->get() as $v)<li><span class="text-muted">v{{ $v->version }} · {{ local_time($v->created_at)->format('j. n. Y') }}</span> – {{ $v->text }}</li>@endforeach</ul>
                </div>
            @endif
            @auth
                @if (auth()->id() !== $question->author_id)
                    <div class="card flex items-center justify-between text-sm"><span>Nepatří to sem?</span><livewire:report-button type="question" :id="$question->id" /></div>
                @endif
            @endauth
        </aside>
    </div>
</x-layouts::app>
