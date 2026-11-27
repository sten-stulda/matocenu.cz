<x-layouts::app title="Objevovat" description="Kategorie, náhodné otázky a otázky bez odpovědi.">
    <h1 class="font-display text-4xl font-extrabold">Objevovat</h1>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-[10px] bg-mustard-100 p-6 text-charcoal lg:col-span-2 dark:bg-surface dark:text-ink">
            <h2 class="font-display text-xl font-bold">Náhodná otázka</h2>
            @if ($random)
                <p class="mt-6 font-display text-3xl leading-tight font-bold">„{{ $random->text }}“</p>
                <div class="mt-6 flex flex-wrap gap-2"><a href="{{ $random->url() }}" class="btn-forest">Odpovědět</a><a href="{{ route('questions.random') }}" class="btn-outline"><x-icon name="dice" :size="18" /> Hodit kostkou znovu</a></div>
            @else
                <p class="mt-3">Zatím tu žádné otázky nejsou.</p>
            @endif
        </section>
        <section class="card-dashed">
            <h2 class="font-display text-xl font-bold">Otázky bez odpovědi</h2>
            <p class="font-hand text-lg text-terra-ink dark:text-mustard">buď první</p>
            <ul class="mt-2">@forelse ($unanswered as $q)<x-question-row :question="$q" meta="answer" />@empty<li class="py-2 text-sm text-muted">Všechny otázky už mají odpověď.</li>@endforelse</ul>
        </section>
    </div>
    <h2 class="h-section mt-10">Kategorie</h2>
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($categories as $c)<x-category-badge :category="$c" class="px-3 py-2 text-sm" />@endforeach
    </div>
    <section class="card mt-8">
        <h2 class="font-display text-xl font-bold">Nejpopulárnější otázky</h2>
        <ul class="mt-2">@forelse ($popular as $q)<x-question-row :question="$q" />@empty<li class="py-2 text-sm text-muted">Zatím bez odpovědí.</li>@endforelse</ul>
    </section>
</x-layouts::app>
