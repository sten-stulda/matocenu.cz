<x-layouts::app :title="$category->name" :description="'Otázky v kategorii '.$category->name.' na MáToCenu.cz.'">
    <nav class="text-[13px] text-muted"><a href="{{ route('categories.index') }}">Kategorie</a> / {{ $category->name }}</nav>
    <h1 class="mt-2 font-display text-4xl font-extrabold">{{ $category->name }}</h1>
    @if ($category->description)<p class="mt-2 text-muted">{{ $category->description }}</p>@endif
    <div class="card mt-6 p-0">
        <ul class="divide-y divide-line px-5">
            @forelse ($questions as $q)<x-question-row :question="$q" />@empty<li class="py-5"><x-empty title="V této kategorii zatím nejsou otázky." /></li>@endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $questions->links() }}</div>
</x-layouts::app>
