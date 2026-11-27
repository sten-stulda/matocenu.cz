<x-layouts::app title="Hlášky" description="Hláška dne, týdne a měsíce – vítězné odpovědi skutečných lidí podle hlasování komunity.">
    <p class="eyebrow">Vybrala komunita reakcemi</p>
    <h1 class="font-display text-4xl font-extrabold sm:text-5xl">Hlášky</h1>
    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @foreach ($current as $period => $q)
            <section @class(['rounded-[10px] p-6', 'bg-mustard text-charcoal' => $period === 'day', 'bg-forest text-cream' => $period === 'week', 'bg-charcoal text-cream' => $period === 'month'])>
                <p class="text-[12px] font-bold uppercase tracking-[.12em] {{ $period === 'day' ? '' : 'text-mustard' }}">{{ \App\Models\Quote::PERIODS[$period] }}</p>
                @if ($q)
                    <p class="mt-2 font-display text-2xl leading-tight font-bold">„{{ $q->display_text }}“</p>
                    <p class="mt-3 text-[13px] font-semibold opacity-90">{{ $q->answer->publicAuthor()?->name ?? 'Anonym' }}@if ($q->style) · {{ $q->style->name }}@endif · {{ cz_plural($q->unique_reactors_count, 'člověk', 'lidé', 'lidí') }}</p>
                    <a href="{{ $q->answer->url() }}" class="mt-3 inline-block text-[13px] underline {{ $period === 'day' ? 'text-charcoal' : 'text-cream' }}">{{ \Illuminate\Support\Str::limit($q->answer->question->text, 70) }}</a>
                @else
                    <p class="mt-3 font-hand text-2xl">Zatím nevyhodnoceno.</p>
                    <p class="mt-1 text-sm opacity-80">Vyhodnocuje se po skončení období z reakcí skutečných lidí.</p>
                @endif
            </section>
        @endforeach
    </div>
    <p class="mt-4 max-w-3xl text-sm text-muted">Vítězné hlášky mohou být navrženy pro budoucí komerční využití (např. potisk). To se nikdy nestane automaticky – vyžaduje právní prověření a samostatnou licenci od autora.</p>
    <h2 class="h-section mt-10">Archiv</h2>
    <div class="card mt-4 p-0">
        <ul class="divide-y divide-line">
            @forelse ($archive as $q)
                <li class="flex flex-wrap items-center gap-3 px-5 py-3"><span class="w-40 text-[12px] font-bold text-terra-ink dark:text-terra">{{ $q->label() }} · {{ $q->period_key }}</span>
                    <a href="{{ $q->answer->url() }}" class="flex-1 font-display text-lg text-ink no-underline hover:underline">„{{ $q->display_text }}“</a>
                    <span class="text-sm text-muted">{{ $q->answer->publicAuthor()?->name ?? 'Anonym' }}</span></li>
            @empty
                <li class="p-5"><x-empty title="Archiv hlášek se teprve plní." /></li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $archive->links() }}</div>
</x-layouts::app>
