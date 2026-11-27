<x-layouts::app title="Kategorie" description="Kategorie otázek na MáToCenu.cz.">
    <h1 class="font-display text-4xl font-extrabold">Kategorie</h1>
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($categories as $c)
            <a href="{{ route('categories.show', $c) }}" class="flex min-h-[120px] flex-col justify-between rounded-[10px] p-4 text-ink no-underline transition hover:brightness-95 {{ $c->tileClass() }}">
                <span class="flex items-start justify-between gap-2 font-display text-lg font-bold">{{ $c->name }}<span class="mt-1.5 h-3 w-3 shrink-0 rounded-sm {{ $c->dotClass() }}"></span></span>
                <span class="text-[12px] text-muted">{{ cz_plural($c->visible_count, 'otázka', 'otázky', 'otázek') }}@if ($c->recent_count) · {{ $c->recent_count }} tento týden @endif</span>
            </a>
        @endforeach
    </div>
</x-layouts::app>
