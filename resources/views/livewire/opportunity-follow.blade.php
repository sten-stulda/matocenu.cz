<?php

use App\Models\Category;
use App\Models\Follow;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?int $categoryId = null;

    #[Locked]
    public ?int $regionId = null;

    public function toggle(string $type): void
    {
        abort_unless(auth()->check() && in_array($type, ['category', 'location'], true), 403);
        $id = $type === 'category' ? $this->categoryId : $this->regionId;
        abort_unless($id, 404);
        $key = ['user_id' => auth()->id(), 'followable_type' => $type, 'followable_id' => $id];
        if (! Follow::where($key)->delete()) {
            DB::table('follows')->insertOrIgnore($key + ['created_at' => now()]);
        }
    }

    public function with(): array
    {
        $f = fn ($type, $id) => $id && Follow::where(['user_id' => auth()->id(), 'followable_type' => $type, 'followable_id' => $id])->exists();

        return [
            'category' => $this->categoryId ? Category::find($this->categoryId) : null,
            'region' => $this->regionId ? Location::find($this->regionId) : null,
            'followsCategory' => $f('category', $this->categoryId),
            'followsRegion' => $f('location', $this->regionId),
        ];
    }
}; ?>

<div class="flex flex-wrap items-center gap-2 text-sm">
    @if ($category || $region)
        <span class="text-muted">Týdenní souhrn nových potřeb e-mailem / na webu:</span>
        @if ($category)<button wire:click="toggle('category')" class="chip" aria-pressed="{{ $followsCategory ? 'true' : 'false' }}"><x-icon name="bell" :size="14" /> {{ $category->name }}</button>@endif
        @if ($region)<button wire:click="toggle('location')" class="chip" aria-pressed="{{ $followsRegion ? 'true' : 'false' }}"><x-icon name="bell" :size="14" /> {{ $region->name }}</button>@endif
    @else
        <span class="text-muted">Vyber kategorii nebo kraj a můžeš je sledovat.</span>
    @endif
</div>
