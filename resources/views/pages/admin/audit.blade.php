<?php

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Auditní log')] class extends Component {
    use WithPagination;

    #[Url]
    public string $action = '';

    public function updatedAction(): void { $this->resetPage(); }

    public function with(): array
    {
        return [
            'logs' => AuditLog::query()->with('user:id,name')
                ->when($this->action !== '', fn ($q) => $q->where('action', 'like', addcslashes($this->action, '%_\\').'%'))
                ->latest('created_at')->paginate(50),
            'actions' => AuditLog::query()->selectRaw("split_part(action, '.', 1) as a")->distinct()->orderBy('a')->pluck('a'),
        ];
    }
}; ?>

<div class="space-y-4">
    <select wire:model.live="action" class="input max-w-xs" aria-label="Oblast">
        <option value="">Všechny akce</option>
        @foreach ($actions as $a)<option value="{{ $a }}">{{ $a }}</option>@endforeach
    </select>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Kdy</th><th>Kdo</th><th>Akce</th><th>Objekt</th><th>Změny</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="whitespace-nowrap">{{ local_time($l->created_at)->format('j. n. Y H:i:s') }}</td>
                    <td>{{ $l->user?->name ?? 'systém' }}</td>
                    <td><code>{{ $l->action }}</code></td>
                    <td class="text-muted">{{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '–' }}</td>
                    <td class="max-w-md"><code class="block truncate text-xs" title="{{ json_encode($l->changes, JSON_UNESCAPED_UNICODE) }}">{{ $l->changes ? json_encode($l->changes, JSON_UNESCAPED_UNICODE) : '' }}</code></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">Zatím žádné záznamy.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
