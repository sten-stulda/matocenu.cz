@props(['size' => 'md', 'tld' => true, 'admin' => false])
@php $px = ['sm' => 28, 'md' => 36, 'lg' => 56][$size] ?? 36; $text = ['sm' => 'text-lg', 'md' => 'text-[22px]', 'lg' => 'text-4xl'][$size] ?? 'text-[22px]'; @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 no-underline']) }}>
    @unless ($admin)<x-logo-mark :size="$px" />@endunless
    <span class="font-display {{ $text }} font-extrabold leading-none tracking-tight">
        <span class="{{ $admin ? 'text-cream' : 'text-forest dark:text-cream' }}">MáTo</span><span class="{{ $admin ? 'text-mustard' : 'text-terra' }}">Cenu</span>@if ($tld)<span class="text-[.55em] {{ $admin ? 'text-cream' : 'text-forest dark:text-cream' }}">.cz</span>@endif
    </span>
    @if ($admin)<span class="ml-1 text-xs font-semibold text-cream/70">admin</span>@endif
    <span class="sr-only">MáToCenu.cz</span>
</span>
