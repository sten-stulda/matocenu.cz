@props(['user' => null, 'anonymous' => false, 'size' => 38])
@php
    $palette = ['bg-mustard text-charcoal', 'bg-forest text-mustard', 'bg-terra text-cream', 'bg-charcoal text-mustard', 'bg-mustard-100 text-terra-ink', 'bg-forest-100 text-forest'];
    $cls = $anonymous || ! $user ? 'bg-charcoal text-cream dark:bg-surface-2' : $palette[$user->id % count($palette)];
@endphp
<span {{ $attributes->merge(['class' => "avatar $cls overflow-hidden"]) }} style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(11, (int) ($size / 2.9)) }}px">
    @if ($anonymous || ! $user)
        ?
    @elseif ($user->avatarUrl())
        <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover" loading="lazy">
    @else
        {{ $user->initials() }}
    @endif
</span>
