<x-layouts::app :title="$doc->title" :description="'MáToCenu.cz – '.$doc->title">
    <div class="grid gap-8 lg:grid-cols-[1fr_280px]">
        <article class="card">
            <p class="eyebrow">Verze {{ $doc->version }} · platná od {{ local_time($doc->published_at)->format('j. n. Y') }}</p>
            <h1 class="mt-2 font-display text-3xl font-extrabold sm:text-4xl">{{ $doc->title }}</h1>
            @if ($doc->is_draft_for_review)
                <p class="mt-3 badge-dashed">Pracovní návrh – čeká na odbornou kontrolu</p>
            @endif
            <div class="prose-mtc mt-6">{!! \Illuminate\Support\Str::markdown($doc->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
        </article>
        <aside class="card h-fit">
            <h2 class="font-display text-lg font-bold">Dokumenty</h2>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($all as $type => [$label, $slug])
                    <li><a href="{{ route('legal.show', $slug) }}" @class(['no-underline', 'font-bold' => $type === $doc->type])>{{ $label }}</a></li>
                @endforeach
            </ul>
        </aside>
    </div>
</x-layouts::app>
