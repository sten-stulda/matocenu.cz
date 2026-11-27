@php $r = $results; $total = $r['questions']->count() + $r['answers']->count(); @endphp
<x-layouts::app :title="$q ? 'Hledat: '.$q : 'Hledat'" :noindex="true">
    <h1 class="font-display text-4xl font-extrabold">Hledat</h1>
    <form method="GET" class="mt-4 flex flex-wrap gap-2" role="search">
        <input type="search" name="q" value="{{ $q }}" class="input max-w-md flex-1" placeholder="Otázky, odpovědi, kategorie… (diakritika nevadí)" aria-label="Hledaný výraz" autofocus>
        <select name="kategorie" class="input w-auto" aria-label="Kategorie"><option value="">Všechny kategorie</option>@foreach ($categories as $c)<option value="{{ $c->slug }}" @selected($filters['kategorie'] === $c->slug)>{{ $c->name }}</option>@endforeach</select>
        <select name="kraj" class="input w-auto" aria-label="Kraj"><option value="">Celá ČR</option>@foreach ($regions as $x)<option value="{{ $x->slug }}" @selected($filters['kraj'] === $x->slug)>{{ $x->name }}</option>@endforeach</select>
        <select name="styl" class="input w-auto" aria-label="Jazykový styl"><option value="">Všechny styly</option>@foreach ($styles as $s)<option value="{{ $s->slug }}" @selected($filters['styl'] === $s->slug)>{{ $s->name }}</option>@endforeach</select>
        <button class="btn-forest">Hledat</button>
    </form>
    @if ($q !== '')
        <p class="mt-4 text-sm text-muted">{{ cz_plural($total, 'výsledek', 'výsledky', 'výsledků') }} pro „{{ $q }}“</p>
        @if ($r['categories']->isNotEmpty())<div class="mt-3 flex flex-wrap gap-2">@foreach ($r['categories'] as $c)<x-category-badge :category="$c" class="px-3 py-2 text-sm" />@endforeach</div>@endif
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="card"><h2 class="font-display text-xl font-bold">Otázky</h2>
                <ul class="mt-2">@forelse ($r['questions'] as $x)<x-question-row :question="$x" />@empty<li class="py-3 text-sm text-muted">Žádná otázka. @if (Route::has('questions.create'))<a class="link" href="{{ route('questions.create') }}">Polož ji</a>.@endif</li>@endforelse</ul></section>
            <section class="card"><h2 class="font-display text-xl font-bold">Odpovědi</h2>
                <ul class="mt-2 divide-y divide-line">@forelse ($r['answers'] as $a)<li class="py-3"><a href="{{ $a->url() }}" class="font-display text-ink no-underline hover:underline">„{{ $a->display_text }}“</a><p class="text-[12px] text-muted">{{ $a->question->text }}</p></li>@empty<li class="py-3 text-sm text-muted">Žádná odpověď.</li>@endforelse</ul></section>
            @includeIf('pages.search-needs')
        </div>
    @endif
</x-layouts::app>
