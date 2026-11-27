<?php

use App\Domain\Needs\Actions\NeedActions;
use App\Domain\Needs\Exceptions\NeedException;
use App\Models\Need;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $needId;

    public string $status = 'open';
    public string $note = '';

    public function mount(): void
    {
        $this->status = Need::findOrFail($this->needId)->status;
    }

    public function save(NeedActions $actions): void
    {
        $this->validate(['status' => 'required|in:'.implode(',', array_keys(Need::STATUSES)), 'note' => 'nullable|string|max:500']);
        try {
            $actions->changeStatus(auth()->user(), Need::findOrFail($this->needId), $this->status, $this->note ?: null);
            session()->flash('status', 'Stav potřeby uložen.');
            $this->redirect(Need::findOrFail($this->needId)->url());
        } catch (NeedException $e) {
            $this->addError('status', $e->getMessage());
        }
    }
}; ?>

<form wire:submit="save" class="card space-y-2 text-sm">
    <h2 class="font-display text-lg font-bold">Tvoje potřeba</h2>
    <select wire:model="status" class="input" aria-label="Stav">@foreach (Need::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
    <input wire:model="note" class="input" placeholder="Poznámka (např. kdo to vyřešil)" aria-label="Poznámka">
    @error('status')<p class="error">{{ $message }}</p>@enderror
    <button class="btn-forest btn-sm">Uložit stav</button>
</form>
