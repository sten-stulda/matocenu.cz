<x-layouts::app :title="'„'.$answer->display_text.'“'" :description="'Odpověď na otázku: '.$answer->question->text" :og-image="Route::has('cards.answer') ? route('cards.answer', $answer) : null" :canonical="$answer->url()" :noindex="! $answer->isPubliclyVisible()">
    <div class="mx-auto max-w-3xl space-y-6">
        <nav class="text-[13px] text-muted"><a href="{{ route('home') }}">Domů</a> / <a href="{{ $answer->question->url() }}">Otázka</a> / Odpověď</nav>
        <a href="{{ $answer->question->url() }}" class="block font-display text-2xl font-extrabold text-ink no-underline hover:underline sm:text-3xl">{{ $answer->question->text }}</a>
        <x-answer-card :answer="$answer" />
        <div class="card"><livewire:comments type="answer" :id="$answer->id" /></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $answer->question->url() }}#odpovedet" class="btn-forest">Odpovědět taky</a>
            @if ($answer->isPubliclyVisible())<a href="{{ route('cards.answer', $answer) }}" class="btn-outline" download="matocenu-hlaska-{{ $answer->id }}.png">Sdílet jako obrázek</a>@endif
        </div>
    </div>
</x-layouts::app>
