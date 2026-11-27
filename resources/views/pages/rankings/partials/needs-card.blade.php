@feature('needs')
@php $topNeed = \App\Models\Need::query()->visible()->whereIn('status', ['open', 'in_progress'])->where('interested_count', '>', 0)->orderByDesc('interested_count')->first(); @endphp
@if ($topNeed)
    <a href="{{ $topNeed->url() }}" class="card block no-underline">
        <p class="eyebrow">Nejčastější potřeba</p>
        <p class="mt-1 font-display text-lg font-bold text-ink">{{ $topNeed->title }}</p>
        <p class="mt-1 text-[13px] text-muted">{{ cz_plural($topNeed->interested_count, 'člověk', 'lidé', 'lidí') }} · {{ $topNeed->would_pay_count }} by zaplatilo</p>
    </a>
@endif
@endfeature
