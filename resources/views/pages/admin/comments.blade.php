<?php

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Models\Comment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Komentáře')] class extends Component {
    use WithPagination;

    public function hide(int $id, Moderation $moderation): void
    {
        $this->authorize('comments.manage');
        $moderation->transition(Comment::findOrFail($id), 'hide', ModerationStatus::Hidden, auth()->user(), 'Skryto správcem komentářů');
    }

    public function restore(int $id, Moderation $moderation): void
    {
        $this->authorize('comments.manage');
        $moderation->transition(Comment::findOrFail($id), 'restore', ModerationStatus::Approved, auth()->user());
    }

    public function with(): array
    {
        return ['comments' => Comment::with('user')->latest()->paginate(40)];
    }
}; ?>

<div class="space-y-4">
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Komentář</th><th>K čemu</th><th>Autor</th><th>Stav</th><th>Kdy</th><th></th></tr></thead>
            <tbody>
            @forelse ($comments as $c)
                <tr wire:key="cm{{ $c->id }}">
                    <td class="max-w-md">{{ $c->body }}</td>
                    <td class="text-sm">{{ ['question' => 'otázka', 'answer' => 'odpověď'][$c->commentable_type] ?? $c->commentable_type }} #{{ $c->commentable_id }}</td>
                    <td>{{ $c->user?->name }}</td>
                    <td><span class="{{ $c->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $c->moderation_status->label() }}</span></td>
                    <td class="whitespace-nowrap">{{ local_time($c->created_at)->format('j. n. H:i') }}</td>
                    <td>@if ($c->moderation_status->isPublic())<button wire:click="hide({{ $c->id }})" class="btn-danger btn-sm">Skrýt</button>@else<button wire:click="restore({{ $c->id }})" class="btn-outline btn-sm">Obnovit</button>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">Zatím žádné komentáře.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $comments->links() }}
</div>
