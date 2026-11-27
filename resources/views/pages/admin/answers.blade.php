<?php

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Answer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Odpovědi')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function hide(int $id, Moderation $moderation): void
    {
        $this->authorize('answers.manage');
        $moderation->transition(Answer::findOrFail($id), 'hide', ModerationStatus::Hidden, auth()->user(), 'Skryto správcem obsahu');
    }

    public function restore(int $id, Moderation $moderation): void
    {
        $this->authorize('answers.manage');
        $moderation->transition(Answer::findOrFail($id), 'restore', ModerationStatus::Approved, auth()->user());
    }

    public function with(): array
    {
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return ['answers' => Answer::with(['user', 'question'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->where('display_text', 'ilike', $like)->orWhere('original_text', 'ilike', $like)))
            ->latest('published_at')->paginate(30)];
    }
}; ?>

<div class="space-y-4">
    <input type="search" wire:model.live.debounce.300ms="search" class="input max-w-xs" placeholder="Hledat v odpovědích…" aria-label="Hledat">
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Odpověď</th><th>Otázka</th><th>Autor</th><th>Stav</th><th class="text-right">Reakce</th><th>Skóre</th><th></th></tr></thead>
            <tbody>
            @forelse ($answers as $a)
                <tr wire:key="an{{ $a->id }}">
                    <td class="max-w-xs"><b>„{{ $a->display_text }}“</b>@if ($a->is_stylized)<br><span class="text-xs text-muted">originál: „{{ $a->original_text }}“</span>@endif</td>
                    <td class="max-w-xs text-sm"><a href="{{ $a->question?->url() }}" target="_blank">{{ \Illuminate\Support\Str::limit($a->question?->text, 60) }}</a></td>
                    <td>{{ $a->user?->name }}@if ($a->is_anonymous) <span class="text-xs text-muted">(anonym.)</span>@endif</td>
                    <td><span class="{{ $a->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $a->moderation_status->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $a->reactions_count }}</td>
                    <td class="tabular-nums">{{ $a->score }}</td>
                    <td>@if ($a->moderation_status->isPublic())<button wire:click="hide({{ $a->id }})" class="btn-danger btn-sm">Skrýt</button>@else<button wire:click="restore({{ $a->id }})" class="btn-outline btn-sm">Obnovit</button>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">Zatím žádné odpovědi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $answers->links() }}
</div>
