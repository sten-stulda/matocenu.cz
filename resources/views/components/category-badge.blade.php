@props(['category'])
<a href="{{ route('categories.show', $category) }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded px-2 py-1 text-[12px] font-semibold text-ink no-underline '.$category->tileClass()]) }}>
    <span class="h-2 w-2 rounded-sm {{ $category->dotClass() }}"></span>{{ $category->name }}
</a>
