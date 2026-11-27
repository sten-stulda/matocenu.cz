@php $q = $dailyQuestion; @endphp
<div class="relative flex flex-col gap-5">
    <div class="flex flex-wrap items-center gap-2.5 text-[13px] font-semibold">
        <span class="rounded bg-mustard px-2.5 py-1.5 font-bold tracking-wider text-charcoal">OTÁZKA DNE · {{ $daily->date->format('j. n.') }}</span>
        <a href="{{ route('categories.show', $q->category) }}" class="rounded border border-forest-300 px-2.5 py-1 text-cream no-underline">{{ $q->category->name }}</a>
        <span class="text-forest-100">{{ cz_plural($q->answers_count, 'odpověď', 'odpovědi', 'odpovědí') }}</span>
        <span class="text-forest-100" x-data="countdown(@js($nextDailyAt->toIso8601String()))">· další otázka za <b class="text-mustard tabular-nums" x-text="left"></b></span>
    </div>
    <h2 id="otazka-dne" class="max-w-4xl font-display text-[34px] leading-[1.05] font-extrabold tracking-tight sm:text-[56px]">
        <a href="{{ $q->url() }}" class="text-cream no-underline hover:underline">{{ $q->text }}</a>
    </h2>
    @includeIf('pages.questions.partials.style-switcher', ['question' => $q, 'onDark' => true])
    <livewire:answer-composer :question-id="$q->id" :dark="true" />
</div>
