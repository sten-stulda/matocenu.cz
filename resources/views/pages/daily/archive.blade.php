<x-layouts::app title="Archiv otázek dne" description="Všechny dosavadní otázky dne na MáToCenu.cz.">
    <h1 class="font-display text-4xl font-extrabold">Archiv otázek dne</h1>
    <div class="card mt-6 p-0">
        <ul class="divide-y divide-line">
            @forelse ($entries as $e)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <span class="flex items-center gap-4"><span class="w-24 font-display font-bold">{{ $e->date->translatedFormat('j. n. Y') }}</span>
                        <a href="{{ $e->question->url() }}" class="font-semibold text-ink no-underline hover:underline">{{ $e->question->text }}</a></span>
                    <span class="text-sm text-muted">{{ cz_plural($e->question->answers_count, 'odpověď', 'odpovědi', 'odpovědí') }}</span>
                </li>
            @empty
                <li class="p-5"><x-empty title="Archiv je zatím prázdný." /></li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts::app>
