<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\ReactionType;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Reakce')] class extends Component {
    public ?int $editId = null;
    public string $label = '';
    public string $icon = 'coin';
    public string $weight = '1.00';
    public bool $counts_as_funny = false;
    public int $sort_order = 0;
    public bool $is_active = true;

    public const ICONS = ['coin', 'smile', 'equal', 'star', 'target', 'check', 'heart', 'sparkle'];

    public function edit(?int $id = null): void
    {
        $t = $id ? ReactionType::findOrFail($id) : new ReactionType(['icon' => 'coin', 'weight' => '1.00', 'is_active' => true, 'sort_order' => (int) ReactionType::max('sort_order') + 1]);
        $this->editId = $id ?? 0;
        $this->label = (string) $t->label;
        $this->icon = $t->icon;
        $this->weight = (string) $t->weight;
        $this->counts_as_funny = (bool) $t->counts_as_funny;
        $this->sort_order = (int) $t->sort_order;
        $this->is_active = (bool) $t->is_active;
    }

    public function save(): void
    {
        $this->authorize('reactions.manage');
        $data = $this->validate([
            'label' => 'required|string|max:40',
            'icon' => 'required|in:'.implode(',', self::ICONS),
            'weight' => 'required|numeric|min:0|max:5',
            'counts_as_funny' => 'boolean',
            'sort_order' => 'integer|min:0|max:99',
            'is_active' => 'boolean',
        ]);
        $t = $this->editId ? ReactionType::findOrFail($this->editId) : new ReactionType(['slug' => Str::slug($data['label']) ?: 'reakce-'.Str::random(4)]);
        $t->fill($data)->save();
        app(AuditLogger::class)->log('reaction_type.saved', $t, $data);
        $this->editId = null;
    }

    public function with(): array
    {
        return ['types' => ReactionType::orderBy('sort_order')->withCount('reactions as used')->get()];
    }
}; ?>

<div class="grid gap-6 xl:grid-cols-[1fr_380px]">
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Reakce</th><th>Váha ve skóre</th><th>Vtipná</th><th class="text-right">Použito</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            @foreach ($types as $t)
                <tr><td class="font-semibold"><x-icon :name="$t->icon" :size="16" class="inline" /> {{ $t->label }}</td><td>{{ $t->weight }}</td><td>{{ $t->counts_as_funny ? 'ano' : '–' }}</td>
                    <td class="text-right">{{ $t->used }}</td><td>{{ $t->is_active ? 'aktivní' : 'vypnutá' }}</td>
                    <td><button wire:click="edit({{ $t->id }})" class="btn-ghost btn-sm">Upravit</button></td></tr>
            @endforeach
            </tbody>
        </table>
        <div class="p-4"><button wire:click="edit" class="btn-accent btn-sm">+ Nová reakce</button></div>
    </div>
    @if ($editId !== null)
        <form wire:submit="save" class="card h-fit space-y-3">
            <div><label class="label" for="l">Text</label><input id="l" wire:model="label" class="input" maxlength="40">@error('label')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="i">Ikona</label><select id="i" wire:model="icon" class="input">@foreach ($this::ICONS as $i)<option>{{ $i }}</option>@endforeach</select></div>
            <div><label class="label" for="w">Váha ve skóre žebříčků</label><input id="w" wire:model="weight" class="input" inputmode="decimal">@error('weight')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="s">Pořadí</label><input id="s" type="number" wire:model="sort_order" class="input"></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="counts_as_funny" class="h-4 w-4 accent-forest"> Počítá se do „nejvtipnějších“</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="h-4 w-4 accent-forest"> Aktivní</label>
            <div class="flex gap-2"><button class="btn-forest">Uložit</button><button type="button" wire:click="$set('editId', null)" class="btn-ghost">Zrušit</button></div>
        </form>
    @endif
</div>
