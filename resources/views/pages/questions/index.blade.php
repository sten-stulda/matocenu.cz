<x-layouts::app :title="\App\Http\Controllers\Community\QuestionController::SORTS[$sort].' otázky'" description="Otázky skutečných lidí na MáToCenu.cz.">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="font-display text-4xl font-extrabold">Otázky</h1>
        @if (Route::has('questions.create'))<a href="{{ route('questions.create') }}" class="btn-accent"><x-icon name="plus" :size="16" /> Přidat otázku</a>@endif
    </div>
    <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <select name="kategorie" class="input w-auto" aria-label="Kategorie" onchange="this.form.submit()">
            <option value="">Kategorie: všechny</option>
            @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($category?->id === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <select name="kraj" class="input w-auto" aria-label="Kraj" onchange="this.form.submit()">
            <option value="">Lokalita: celá ČR</option>
            @foreach ($regions as $r)<option value="{{ $r->slug }}" @selected($region?->id === $r->id)>{{ $r->name }}</option>@endforeach
        </select>
        <input type="hidden" name="razeni" value="{{ $sort }}">
        <noscript><button class="btn-outline btn-sm">Filtrovat</button></noscript>
        <span class="mx-1 hidden h-6 w-px bg-line sm:block"></span>
        @foreach (\App\Http\Controllers\Community\QuestionController::SORTS as $k => $l)
            <a href="{{ request()->fullUrlWithQuery(['razeni' => $k, 'page' => null]) }}" @class(['btn btn-sm', 'bg-forest text-cream' => $sort === $k, 'text-ink hover:bg-surface-2' => $sort !== $k])>{{ $l }}</a>
        @endforeach
    </form>
    <div class="card mt-5 p-0">
        <ul class="divide-y divide-line">
            @forelse ($questions as $q)
                <li class="flex flex-wrap items-center gap-3 px-5 py-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ $q->url() }}" class="font-display text-lg font-bold text-ink no-underline hover:underline">{{ $q->text }}</a>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-muted"><x-category-badge :category="$q->category" />@if ($q->location)<span class="badge-outline">{{ $q->location->name }}</span>@endif {{ local_time($q->published_at)->diffForHumans() }}</p>
                    </div>
                    <span class="text-sm font-bold text-terra-ink dark:text-terra">{{ cz_plural($q->answers_count, 'odpověď', 'odpovědi', 'odpovědí') }}</span>
                </li>
            @empty
                <li class="p-5"><x-empty title="Tož tady zatím nikdo nic neřekl." hand="První otázka čeká právě na tebe.">@if (Route::has('questions.create'))<a href="{{ route('questions.create') }}" class="link">Polož otázku</a>@endif</x-empty></li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts::app>
