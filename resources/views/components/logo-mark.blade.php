@props(['size' => 36, 'dark' => false])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" aria-hidden="true" {{ $attributes }}>
    <path d="M10 6h44a6 6 0 0 1 6 6v30a6 6 0 0 1-6 6H31l-13 11V48h-8a6 6 0 0 1-6-6V12a6 6 0 0 1 6-6z" fill="{{ $dark ? '#E5B54B' : '#244A35' }}"/>
    <path d="M24 21c0-4.5 3.6-7.5 8.5-7.5S41 16.5 41 21c0 6-8 5.5-8 11" fill="none" stroke="{{ $dark ? '#252525' : '#F7F3E9' }}" stroke-width="5" stroke-linecap="round"/>
    <circle cx="33" cy="40" r="5" fill="{{ $dark ? '#252525' : '#E5B54B' }}"/>
    <circle cx="33" cy="40" r="2.6" fill="none" stroke="{{ $dark ? '#E5B54B' : '#244A35' }}" stroke-width="1"/>
</svg>
