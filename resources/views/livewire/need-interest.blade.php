<?php

use App\Domain\Needs\Actions\InterestActions;
use App\Domain\Needs\Enums\Frequency;
use App\Domain\Needs\Enums\InterestKind;
use App\Domain\Needs\Exceptions\NeedException;
use App\Models\Need;
use App\Models\NeedInterest;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $needId;

    /** @var list<string> */
    public array $mine = [];
    public bool $payOpen = false;
    public ?string $amount = null;
    public string $currency = 'CZK';
    public ?string $frequency = null;
    public bool $commitment = false;
    public bool $consent = false;
    public ?string $error = null;

    public function mount(): void
    {
        $this->load();
    }

    private function load(): void
    {
        if (! auth()->check()) {
            return;
        }
        $rows = NeedInterest::where('need_id', $this->needId)->where('user_id', auth()->id())->get();
        $this->mine = $rows->map(fn ($r) => $r->kind->value)->all();
        $pay = $rows->first(fn ($r) => $r->kind === InterestKind::WouldPay);
        $this->amount = $pay?->amount;
        $this->frequency = $pay?->frequency?->value;
        $this->commitment = (bool) $rows->first(fn ($r) => $r->is_commitment);
        $this->consent = (bool) $rows->first(fn ($r) => $r->contact_consent);
    }

    public function toggle(string $kind, InterestActions $actions): void
    {
        $this->error = null;
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }
        $k = InterestKind::from($kind);
        $need = Need::findOrFail($this->needId);
        try {
            if (in_array($kind, $this->mine, true)) {
                $actions->remove(auth()->user(), $need, $k);
            } elseif ($k === InterestKind::WouldPay) {
                $this->payOpen = true;

                return;
            } else {
                $actions->set(auth()->user(), $need, $k, ['contact_consent' => $this->consent]);
            }
        } catch (NeedException $e) {
            $this->error = $e->getMessage();
        }
        $this->load();
    }

    public function savePay(InterestActions $actions): void
    {
        $this->validate([
            'amount' => 'nullable|numeric|min:0|max:1000000',
            'currency' => 'in:CZK,EUR',
            'frequency' => 'nullable|in:'.implode(',', array_column(Frequency::cases(), 'value')),
        ], [], ['amount' => 'částka', 'frequency' => 'frekvence']);
        try {
            $actions->set(auth()->user(), Need::findOrFail($this->needId), InterestKind::WouldPay, [
                'amount' => $this->amount, 'currency' => $this->currency, 'frequency' => $this->frequency,
                'is_commitment' => $this->commitment, 'contact_consent' => $this->consent,
            ]);
            $this->payOpen = false;
        } catch (NeedException $e) {
            $this->error = $e->getMessage();
        }
        $this->load();
    }

    public function saveConsent(InterestActions $actions): void
    {
        $actions->setConsent(auth()->user(), Need::findOrFail($this->needId), $this->consent);
    }
}; ?>

<section id="zajem" class="card space-y-3" aria-labelledby="zajem-h">
    <h2 id="zajem-h" class="font-display text-xl font-bold">Přidáš se?</h2>
    <div class="flex flex-wrap gap-2">
        @foreach (InterestKind::cases() as $k)
            <button type="button" wire:click="toggle('{{ $k->value }}')" aria-pressed="{{ in_array($k->value, $mine, true) ? 'true' : 'false' }}" class="chip">{{ $k->label() }}</button>
        @endforeach
    </div>
    @if ($error)<p class="text-sm font-semibold text-terra-ink" role="alert">{{ $error }}</p>@endif

    @if ($payOpen || in_array('would_pay', $mine, true))
        <form wire:submit="savePay" class="space-y-3 rounded-md bg-surface-2 p-4">
            <p class="text-sm font-semibold">Kolik bys za řešení zaplatil/a? <span class="font-normal text-muted">(nepovinné, nezávazné)</span></p>
            <div class="flex flex-wrap gap-2">
                <input wire:model="amount" inputmode="decimal" class="input w-36" placeholder="např. 400" aria-label="Částka">
                <select wire:model="currency" class="input w-24" aria-label="Měna"><option>CZK</option><option>EUR</option></select>
                <select wire:model="frequency" class="input w-auto" aria-label="Jak často"><option value="">jak často?</option>@foreach (Frequency::cases() as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach</select>
            </div>
            @error('amount')<p class="error">{{ $message }}</p>@enderror
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="commitment" class="mt-0.5 h-4 w-4 accent-forest"><span><b>Závazný zájem</b> – kdyby řešení vzniklo za tuto cenu, opravdu ho využiju. (Stále nejde o objednávku.)</span></label>
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="consent" class="mt-0.5 h-4 w-4 accent-forest"><span>Realizátoři mi smí napsat <b>přes platformu</b> (nedozví se můj e-mail ani jméno).</span></label>
            <div class="flex gap-2"><button class="btn-forest btn-sm">Uložit</button>@if (! in_array('would_pay', $mine, true))<button type="button" wire:click="$set('payOpen', false)" class="btn-ghost btn-sm">Zrušit</button>@endif</div>
        </form>
    @elseif ($mine)
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" wire:model="consent" wire:change="saveConsent" class="mt-0.5 h-4 w-4 accent-forest"><span>Realizátoři mi smí napsat přes platformu (bez odhalení kontaktu).</span></label>
    @endif
    <p class="text-[12px] text-muted">Každý se počítá jednou. Ochota zaplatit je nezávazná a ukazujeme ji jen souhrnně (od {{ \App\Domain\Needs\Services\NeedStats::K_AMOUNT }} odpovědí).</p>
</section>
