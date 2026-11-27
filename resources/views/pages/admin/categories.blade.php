<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Kategorie')] class extends Component {
    public ?int $editId = null;
    public string $name = '';
    public string $description = '';
    public string $color = 'forest';
    public int $sort_order = 0;
    public bool $is_active = true;
    public bool $is_sensitive = false;

    public function edit(?int $id = null): void
    {
        $c = $id ? Category::findOrFail($id) : new Category(['color' => 'forest', 'is_active' => true, 'sort_order' => (int) Category::max('sort_order') + 1]);
        $this->editId = $id ?? 0;
        $this->fill($c->only(['name', 'description', 'color', 'sort_order', 'is_active', 'is_sensitive']));
        $this->description = (string) $c->description;
        $this->is_active = (bool) $c->is_active;
        $this->is_sensitive = (bool) $c->is_sensitive;
    }

    public function save(): void
    {
        $this->authorize('categories.manage');
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'color' => ['required', Rule::in(array_keys(Category::COLORS))],
            'sort_order' => ['integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
            'is_sensitive' => ['boolean'],
        ]);
        $c = $this->editId ? Category::findOrFail($this->editId) : new Category;
        if (! $c->exists) {
            $base = Str::slug($data['name']);
            $slug = $base;
            $i = 2;
            while (Category::where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }
            $c->slug = $slug;
        }
        $c->fill($data)->save();
        app(AuditLogger::class)->log('category.saved', $c, $data);
        $this->editId = null;
    }

    public function with(): array
    {
        return ['categories' => Category::withCount('questions')->orderBy('sort_order')->get()];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Pořadí</th><th>Název</th><th>Barva</th><th class="text-right">Otázek</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            @foreach ($categories as $c)
                <tr><td>{{ $c->sort_order }}</td><td class="font-semibold">{{ $c->name }} @if ($c->is_sensitive)<span class="badge-terra">citlivá</span>@endif</td>
                    <td><span class="inline-block h-4 w-4 rounded-sm {{ $c->dotClass() }}"></span> {{ $c->color }}</td>
                    <td class="text-right">{{ $c->questions_count }}</td><td>{{ $c->is_active ? 'aktivní' : 'skrytá' }}</td>
                    <td><button wire:click="edit({{ $c->id }})" class="btn-ghost btn-sm">Upravit</button></td></tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-4"><button wire:click="edit" class="btn-accent btn-sm">+ Nová kategorie</button></div>
    </div>
    @if ($editId !== null)
        <form wire:submit="save" class="card h-fit space-y-3">
            <h2 class="font-display text-lg font-bold">{{ $editId ? 'Upravit' : 'Nová' }} kategorie</h2>
            <div><label class="label" for="n">Název</label><input id="n" wire:model="name" class="input">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="d">Popis</label><input id="d" wire:model="description" class="input"></div>
            <div><label class="label" for="co">Barva</label><select id="co" wire:model="color" class="input">@foreach (array_keys(Category::COLORS) as $k)<option>{{ $k }}</option>@endforeach</select></div>
            <div><label class="label" for="so">Pořadí</label><input id="so" type="number" wire:model="sort_order" class="input"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="h-4 w-4 accent-forest"> Aktivní</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_sensitive" class="h-4 w-4 accent-forest"> Citlivá (nové otázky mají humor vypnutý)</label>
            <div class="flex gap-2"><button class="btn-forest">Uložit</button><button type="button" wire:click="$set('editId', null)" class="btn-ghost">Zrušit</button></div>
        </form>
    @endif
</div>
