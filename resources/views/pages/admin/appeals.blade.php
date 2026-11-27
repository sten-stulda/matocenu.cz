<?php

use App\Domain\Moderation\Actions\AppealDecision;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Models\ModerationAppeal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Odvolání')] class extends Component {
    /** @var array<int, string> */
    public array $note = [];

    public function decide(int $id, bool $overturn, AppealDecision $decision): void
    {
        $this->authorize('moderation.manage');
        try {
            $decision->decide(auth()->user(), ModerationAppeal::with('action.moderatable')->findOrFail($id), $overturn, $this->note[$id] ?? null);
        } catch (ModerationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        return ['appeals' => ModerationAppeal::with(['action.moderatable', 'action.moderator', 'user'])->orderByRaw("status = 'open' desc")->latest()->limit(100)->get()];
    }
}; ?>

<div class="space-y-4">
    <p class="text-sm text-muted">O odvolání rozhoduje jiný moderátor než ten, kdo zasáhl.</p>
    @forelse ($appeals as $a)
        <div class="card space-y-2" wire:key="ap{{ $a->id }}">
            <p class="text-sm"><b>{{ $a->user->name }}</b> · {{ local_time($a->created_at)->diffForHumans() }} · <span class="badge-mustard">{{ ['open' => 'čeká', 'upheld' => 'zamítnuto', 'overturned' => 'vyhověno'][$a->status] }}</span></p>
            <p class="font-display">„{{ $a->action->moderatable?->moderationExcerpt() ?? 'smazáno' }}“</p>
            <p class="text-sm">Rozhodnutí: {{ $a->action->label() }} ({{ $a->action->moderator?->name ?? 'automaticky' }}) – {{ $a->action->reason }}</p>
            <p class="rounded bg-surface-2 p-3 text-sm">{{ $a->message }}</p>
            @if ($a->status === 'open')
                <input wire:model="note.{{ $a->id }}" class="input" placeholder="Zdůvodnění rozhodnutí (uvidí autor)" aria-label="Zdůvodnění">
                <div class="flex gap-2"><button wire:click="decide({{ $a->id }}, true)" class="btn-forest btn-sm">Vyhovět – obnovit</button><button wire:click="decide({{ $a->id }}, false)" class="btn-danger btn-sm">Zamítnout odvolání</button></div>
            @elseif ($a->decision_note)<p class="text-sm text-muted">{{ $a->decision_note }}</p>@endif
        </div>
    @empty
        <x-empty title="Žádná odvolání." />
    @endforelse
</div>
