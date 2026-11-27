@php $maxLen = (int) setting('answers.max_length'); @endphp
<x-layouts::app current="home">
    <h1 class="sr-only">MáToCenu.cz – {{ setting('brand.slogan') }}</h1>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_320px]">
        {{-- Question of the day --}}
        <section aria-labelledby="otazka-dne" class="relative overflow-hidden rounded-[10px] bg-forest p-6 text-cream sm:p-10">
            <svg width="220" height="220" viewBox="0 0 64 64" aria-hidden="true" class="pointer-events-none absolute -right-8 -top-8 opacity-[.08]"><path d="M24 21c0-4.5 3.6-7.5 8.5-7.5S41 16.5 41 21c0 6-8 5.5-8 11" fill="none" stroke="#F7F3E9" stroke-width="5" stroke-linecap="round"/><circle cx="33" cy="42" r="5" fill="#F7F3E9"/></svg>
            @if ($daily)
                @include('pages.home.daily-hero')
            @else
                <span class="rounded bg-mustard px-2.5 py-1.5 text-[13px] font-bold tracking-wider text-charcoal">OTÁZKA DNE</span>
                <h2 id="otazka-dne" class="mt-5 max-w-3xl font-display text-4xl leading-[1.05] font-extrabold tracking-tight sm:text-[52px]">Otázka dne se právě vaří.</h2>
                <p class="mt-4 max-w-2xl text-forest-100">Redakce ji ještě nenaplánovala. Mezitím si založ účet – až se objeví, budeš u toho mezi prvními.</p>
                @guest
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('register') }}" class="btn-accent">Založit účet</a>
                        <a href="{{ route('login') }}" class="btn border-[1.5px] border-forest-300 text-cream no-underline hover:border-cream">Přihlásit se</a>
                    </div>
                @endguest
            @endif
        </section>

        <aside class="space-y-6">
            @php $quoteAnswer = ! empty($quote) ? $quote->answer : ($quoteOfDay ?? null); @endphp
            @if ($quoteAnswer)
                @php $quoteOfDay = $quoteAnswer; @endphp
                <a href="{{ $quoteOfDay->url() }}" class="block rounded-[10px] bg-mustard p-6 text-charcoal no-underline">
                    <p class="text-[12px] font-bold uppercase tracking-[.12em]">{{ ! empty($quote) ? 'Hláška dne' : 'Nejvtipnější odpověď dne' }}</p>
                    <p class="mt-2 font-display text-2xl leading-tight font-bold">„{{ $quoteOfDay->display_text }}“</p>
                    <p class="mt-3 text-[13px] font-semibold">{{ $quoteOfDay->publicAuthor()?->name ?? 'Anonym' }} · {{ cz_plural($quoteOfDay->reactions_count, 'reakce', 'reakce', 'reakcí') }}</p>
                </a>
            @endif
            @includeIf('pages.home.leaderboard-widget')
            <div class="rounded-[10px] bg-mustard p-6 text-charcoal {{ ! empty($quoteAnswer) ? 'hidden lg:block lg:bg-mustard-100 dark:lg:bg-surface dark:lg:text-ink' : '' }}">
                <p class="text-[12px] font-bold uppercase tracking-[.12em]">Jak to funguje</p>
                <ol class="mt-3 space-y-2 text-[15px] font-semibold">
                    <li>1. Každý den jedna otázka.</li>
                    <li>2. Odpovíš po svém – max. {{ $maxLen }} znaků.</li>
                    <li>3. Chceš-li, AI ji přeloží do moravštiny, staročeštiny či hospodské hlášky.</li>
                    <li>4. Lidé reagují. Nejlepší hlášky vyhrávají.</li>
                </ol>
            </div>
            <div class="card">
                <p class="font-hand text-2xl text-terra-ink dark:text-mustard">AI je vypravěčem,</p>
                <p class="font-display text-lg font-bold">člověk zůstává autorem myšlenky.</p>
                <p class="mt-2 text-sm text-muted">Žádné vymyšlené odpovědi ani falešná aktivita. Každá stylizace je označená a autor ji před zveřejněním potvrdí.</p>
                <a href="{{ route('legal.show', 'jak-pracuje-ai') }}" class="link mt-3 inline-block text-sm">Jak pracuje AI</a>
            </div>
        </aside>
    </div>

    @isset($sections)
        @include('pages.home.sections')
    @endisset
</x-layouts::app>
