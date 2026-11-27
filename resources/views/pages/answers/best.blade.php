@php $reactionData = app(\App\Domain\Reactions\Services\ReactionSummary::class)->for('answer', $answers, auth()->user()); @endphp
<x-layouts::app title="Nejvtipnější odpovědi" description="Nejlépe hodnocené odpovědi skutečných lidí na MáToCenu.cz.">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="eyebrow">Hodnotí komunita</p>
            <h1 class="font-display text-4xl font-extrabold">Nejlépe hodnocené odpovědi</h1>
            <p class="mt-1 text-sm text-muted">Pořadí podle reakcí unikátních lidí (nové účty mají poloviční váhu, vlastní odpovědi hodnotit nejde).</p>
        </div>
        <nav class="flex gap-1" aria-label="Období">
            @foreach (['den' => 'Den', 'tyden' => 'Týden', 'mesic' => 'Měsíc'] as $k => $l)
                <a href="?obdobi={{ $k }}" @class(['btn-sm btn', 'bg-forest text-cream' => $period === $k, 'text-ink hover:bg-surface-2' => $period !== $k])>{{ $l }}</a>
            @endforeach
        </nav>
    </div>
    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($answers as $a)
            <x-answer-card :answer="$a" :show-question="true" :reaction-data="$reactionData[$a->id] ?? null" />
        @empty
            <x-empty class="md:col-span-2 xl:col-span-3" hand="První hláška čeká právě na tebe." title="V tomto období zatím nikdo nesbíral reakce." />
        @endforelse
    </div>
</x-layouts::app>
