@props(['title' => 'Tož tady zatím nikdo nic neřekl.', 'hand' => null])
<div {{ $attributes->merge(['class' => 'card-dashed text-center']) }}>
    @if ($hand)<p class="font-hand text-2xl text-terra-ink dark:text-mustard">{{ $hand }}</p>@endif
    <p class="mt-1 font-semibold">{{ $title }}</p>
    @if (trim($slot))<div class="mt-2 text-sm text-muted">{{ $slot }}</div>@endif
</div>
