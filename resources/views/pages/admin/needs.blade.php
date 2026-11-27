<?php

use App\Domain\Moderation\Enums\ModerationStatus;
use App\Domain\Moderation\Services\Moderation;
use App\Domain\Needs\Actions\NeedActions;
use App\Models\Need;
use App\Models\NeedOffer;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Potřeby')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    public function hide(string $type, int $id, Moderation $moderation): void
    {
        $this->authorize('needs.manage');
        $m = $type === 'need' ? Need::findOrFail($id) : NeedOffer::findOrFail($id);
        $moderation->transition($m, 'hide', ModerationStatus::Hidden, auth()->user(), 'Skryto správcem potřeb');
    }

    public function restore(string $type, int $id, Moderation $moderation): void
    {
        $this->authorize('needs.manage');
        $m = $type === 'need' ? Need::findOrFail($id) : NeedOffer::findOrFail($id);
        $moderation->transition($m, 'restore', ModerationStatus::Approved, auth()->user());
    }

    public function setStatus(int $id, string $to, NeedActions $actions): void
    {
        $this->authorize('needs.manage');
        $actions->changeStatus(auth()->user(), Need::findOrFail($id), $to, 'Změněno správcem');
    }

    public function with(): array
    {
        return [
            'needs' => Need::with(['user', 'category', 'location'])->withCount('offers')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->latest()->paginate(25),
            'offers' => NeedOffer::with(['user', 'need'])->latest()->limit(20)->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <select wire:model.live="status" class="input max-w-xs" aria-label="Stav"><option value="">Všechny stavy</option>@foreach (Need::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Potřeba</th><th>Autor</th><th class="text-right">Zájem</th><th class="text-right">Platit</th><th class="text-right">Nabídky</th><th>Stav</th><th>Moderace</th><th></th></tr></thead>
            <tbody>
            @forelse ($needs as $n)
                <tr wire:key="n{{ $n->id }}"><td class="max-w-sm"><a href="{{ $n->url() }}" target="_blank" class="font-semibold">{{ $n->title }}</a><br><span class="text-xs text-muted">{{ $n->category->name }} · {{ $n->location?->name ?? 'ČR' }}</span></td>
                    <td>{{ $n->user->name }}@if ($n->is_anonymous) <span class="text-xs text-muted">(anonym.)</span>@endif</td>
                    <td class="text-right">{{ $n->interested_count }}</td><td class="text-right">{{ $n->would_pay_count }}</td><td class="text-right">{{ $n->offers_count }}</td>
                    <td><select wire:change="setStatus({{ $n->id }}, $event.target.value)" class="input py-1 text-sm" aria-label="Stav">@foreach (Need::STATUSES as $k => $l)<option value="{{ $k }}" @selected($n->status === $k)>{{ $l }}</option>@endforeach</select></td>
                    <td><span class="{{ $n->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $n->moderation_status->label() }}</span></td>
                    <td>@if ($n->moderation_status->isPublic())<button wire:click="hide('need', {{ $n->id }})" class="btn-danger btn-sm">Skrýt</button>@else<button wire:click="restore('need', {{ $n->id }})" class="btn-outline btn-sm">Obnovit</button>@endif</td></tr>
            @empty
                <tr><td colspan="8" class="text-muted">Žádné potřeby.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $needs->links() }}
    <section class="card"><h2 class="font-display text-xl font-bold">Poslední nabídky realizátorů</h2>
        <table class="table-admin mt-3"><thead><tr><th>Nabídka</th><th>K potřebě</th><th>Realizátor</th><th>Stav</th><th></th></tr></thead><tbody>
        @forelse ($offers as $o)
            <tr><td class="max-w-sm text-sm">{{ \Illuminate\Support\Str::limit($o->description, 120) }}</td><td class="text-sm">{{ $o->need->title }}</td><td>{{ $o->user->name }}</td><td><span class="badge-outline">{{ NeedOffer::STATUSES[$o->status] }}</span> <span class="{{ $o->moderation_status->isPublic() ? 'badge-forest' : 'badge-terra' }}">{{ $o->moderation_status->label() }}</span></td>
                <td>@if ($o->moderation_status->isPublic())<button wire:click="hide('offer', {{ $o->id }})" class="btn-danger btn-sm">Skrýt</button>@else<button wire:click="restore('offer', {{ $o->id }})" class="btn-outline btn-sm">Obnovit</button>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-muted">Zatím žádné nabídky.</td></tr>@endforelse
        </tbody></table></section>
</div>
