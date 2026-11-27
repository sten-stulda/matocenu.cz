<article class="card">
    <div class="flex flex-wrap items-center gap-2 text-[12px]">
        <span class="rounded px-2 py-1 font-semibold {{ $need->category->tileClass() }}">{{ $need->category->name }}</span>
        <span class="badge-outline">{{ $need->location?->name ?? 'Celá ČR' }}</span>
        <span class="text-muted">{{ local_time($need->published_at)->diffForHumans() }}</span>
        @if ($need->status === 'in_progress')<span class="badge-forest">Řeší se</span>@endif
    </div>
    <div class="mt-2 flex gap-4">
        <div class="min-w-0 flex-1">
            <h2 class="font-display text-2xl font-bold"><a href="{{ $need->url() }}" class="text-ink no-underline hover:underline">{{ $need->title }}</a></h2>
            <p class="mt-1 text-[15px] text-muted">{{ \Illuminate\Support\Str::limit($need->description, 220) }}</p>
        </div>
        <div class="shrink-0 text-right"><p class="font-display text-4xl font-extrabold text-forest dark:text-primary">{{ cz_number($need->interested_count) }}</p><p class="text-[12px] text-muted">má zájem o řešení</p></div>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
        <a href="{{ $need->url() }}#zajem" class="btn-forest btn-sm">Taky to potřebuju</a>
        <a href="{{ $need->url() }}#zajem" class="btn btn-sm border-[1.5px] border-ink/70 text-ink">Zaplatil/a bych · {{ $need->would_pay_count }}</a>
        <a href="{{ $need->url() }}#zajem" class="btn btn-sm border-[1.5px] border-line text-ink">Souhlasím · {{ $need->like_count }}</a>
        <a href="{{ $need->url() }}#nabidky" class="btn-accent btn-sm ml-auto">Nabízím řešení · {{ $need->offers_count }}</a>
    </div>
    @if ($pay)
        <p class="mt-2 text-[12px] text-muted">Ochota platit: <b class="text-ink">{{ cz_number($pay['p25']) }}–{{ cz_number($pay['p75']) }} Kč{{ $pay['frequency'] ? ' '.$pay['frequency']->short() : '' }}</b> (medián {{ cz_number($pay['median']) }} Kč z {{ $pay['amount_samples'] }} nezávazných odpovědí)</p>
    @endif
</article>
