<?php

use App\Domain\Quotes\Services\QuoteSelector;
use App\Models\Quote;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Hlášky')] class extends Component {
    use WithPagination;

    public function award(string $period, QuoteSelector $selector): void
    {
        $this->authorize('quotes.manage');
        abort_unless(array_key_exists($period, Quote::PERIODS), 422);
        $q = $selector->award($period);
        session()->flash($q ? 'status' : 'error', $q ? 'Vyhodnoceno: „'.$q->display_text.'“' : 'Za uplynulé období už je vyhodnoceno, nebo nebyla žádná hodnocená odpověď.');
    }

    public function with(): array
    {
        return ['quotes' => Quote::with(['answer.user', 'answer.question'])->latest('awarded_at')->paginate(30)];
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm text-muted">Vyhodnocuje se automaticky v 00:20. Ručně za uplynulé období:</span>
        @foreach (Quote::PERIODS as $p => $l)<button wire:click="award('{{ $p }}')" class="btn-outline btn-sm">{{ $l }}</button>@endforeach
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Hláška</th><th>Období</th><th>Autor</th><th>Skóre</th><th>Právní stav</th><th>Licence</th><th>Komerčně</th></tr></thead>
            <tbody>
            @forelse ($quotes as $q)
                <tr><td class="max-w-sm"><b>„{{ $q->display_text }}“</b>@if ($q->is_stylized)<br><span class="text-xs text-muted">originál: „{{ $q->original_text }}“</span>@endif</td>
                    <td>{{ $q->label() }}<br><span class="text-xs text-muted">{{ $q->period_key }}</span></td>
                    <td>{{ $q->answer?->user?->name }}@if ($q->answer?->is_anonymous) <span class="text-xs text-muted">(veřejně anonym)</span>@endif</td>
                    <td class="tabular-nums">{{ $q->score }}<br><span class="text-xs text-muted">{{ $q->unique_reactors_count }} lidí</span></td>
                    <td><span class="badge-outline">{{ Quote::LEGAL[$q->legal_status] }}</span></td>
                    <td><span class="badge-outline">{{ Quote::LICENSE[$q->license_status] }}</span></td>
                    <td><span class="{{ $q->commercial_status === 'approved' ? 'badge-forest' : 'badge-terra' }}">{{ Quote::COMMERCIAL[$q->commercial_status] }}</span></td></tr>
            @empty
                <tr><td colspan="7"><x-empty title="Zatím žádné vyhodnocené hlášky." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $quotes->links() }}
</div>
