<?php

use App\Domain\Administration\Services\AuditLogger;
use App\Models\PrivacyRequest;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Žádosti GDPR')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = 'open';

    /** @var array<int, string> */
    public array $resolution = [];

    public function setStatus(int $id, string $status): void
    {
        $this->authorize('privacy.manage');
        abort_unless(array_key_exists($status, PrivacyRequest::STATUSES), 422);
        $req = PrivacyRequest::findOrFail($id);
        $req->update([
            'status' => $status,
            'resolution' => trim($this->resolution[$id] ?? '') ?: $req->resolution,
            'handled_by' => auth()->id(),
            'resolved_at' => in_array($status, ['done', 'rejected'], true) ? now() : null,
        ]);
        app(AuditLogger::class)->log('privacy.request_'.$status, $req);
    }

    public function with(): array
    {
        return ['requests' => PrivacyRequest::with('user:id,name,email')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->latest()->paginate(25)];
    }
}; ?>

<div class="space-y-4">
    <p class="text-sm text-muted">Lhůta pro vyřízení je 30 dnů. Export a smazání účtu si uživatelé dělají sami v Profil → Soukromí; sem chodí ostatní žádosti.</p>
    <select wire:model.live="status" class="input max-w-xs" aria-label="Stav">
        <option value="">Všechny</option>
        @foreach (PrivacyRequest::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
    </select>
    @forelse ($requests as $r)
        <div class="card space-y-2" wire:key="pr{{ $r->id }}">
            <p class="text-sm"><b>{{ PrivacyRequest::TYPES[$r->type] }}</b> · {{ $r->user?->name ?? 'smazaný účet' }} · {{ local_time($r->created_at)->format('j. n. Y') }}
                @php $due = $r->created_at->copy()->addDays(30); @endphp
                · <span class="{{ $r->status === 'open' && $due->isPast() ? 'font-bold text-terra-ink' : 'text-muted' }}">lhůta {{ local_time($due)->format('j. n.') }}</span>
                · <span class="badge-mustard">{{ PrivacyRequest::STATUSES[$r->status] }}</span></p>
            <p class="whitespace-pre-line text-sm">{{ $r->message }}</p>
            <textarea wire:model="resolution.{{ $r->id }}" rows="2" class="input" placeholder="Odpověď / způsob vyřízení (uvidí žadatel)">{{ $r->resolution }}</textarea>
            <div class="flex flex-wrap gap-2">
                <button wire:click="setStatus({{ $r->id }}, 'in_progress')" class="btn-outline btn-sm">Řeším</button>
                <button wire:click="setStatus({{ $r->id }}, 'done')" class="btn-forest btn-sm">Vyřízeno</button>
                <button wire:click="setStatus({{ $r->id }}, 'rejected')" class="btn-danger btn-sm">Zamítnout</button>
            </div>
        </div>
    @empty
        <x-empty title="Žádné žádosti v tomto stavu." />
    @endforelse
    {{ $requests->links() }}
</div>
