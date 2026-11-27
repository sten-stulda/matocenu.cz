<?php

use App\Domain\Needs\Actions\OfferActions;
use App\Domain\Needs\Exceptions\NeedException;
use App\Models\Need;
use App\Models\NeedInterest;
use App\Models\NeedOffer;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $needId;

    public bool $form = false;
    public string $description = '';
    public string $price = '';
    /** @var array<int, string> */
    public array $message = [];
    public ?string $error = null;
    public ?string $info = null;

    public function create(OfferActions $offers): void
    {
        abort_unless(auth()->check(), 403);
        $this->validate(['description' => 'required|string|min:15|max:1500', 'price' => 'nullable|string|max:120'], [], ['description' => 'popis nabídky']);
        try {
            $offers->create(auth()->user(), Need::findOrFail($this->needId), $this->description, $this->price ?: null);
            $this->reset('description', 'price', 'form');
            $this->info = 'Nabídka zveřejněna. Autor potřeby dostal upozornění.';
        } catch (NeedException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function decide(int $id, bool $accept, OfferActions $offers): void
    {
        try {
            $offers->decide(auth()->user(), NeedOffer::with('need', 'user')->findOrFail($id), $accept);
        } catch (NeedException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function withdraw(int $id, OfferActions $offers): void
    {
        $offers->withdraw(auth()->user(), NeedOffer::findOrFail($id));
    }

    public function broadcast(int $id, OfferActions $offers): void
    {
        try {
            $n = $offers->broadcast(auth()->user(), NeedOffer::with('need')->findOrFail($id), $this->message[$id] ?? '');
            $this->message[$id] = '';
            $this->info = $n ? "Zprávu jsme předali {$n} zájemcům, kteří souhlasili s kontaktem." : 'Zatím nikdo nesouhlasil s kontaktem – zpráva nebyla doručena nikomu.';
        } catch (NeedException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function with(): array
    {
        $need = Need::findOrFail($this->needId);

        return [
            'need' => $need,
            'offers' => NeedOffer::with('user')->where('need_id', $need->id)->where('status', '!=', 'withdrawn')
                ->where(fn ($q) => $q->publiclyVisible()->when(auth()->id(), fn ($w, $id) => $w->orWhere('user_id', $id)))->latest()->get(),
            'consenting' => NeedInterest::where('need_id', $need->id)->where('contact_consent', true)->distinct()->count('user_id'),
            'mine' => auth()->check() ? NeedOffer::where('need_id', $need->id)->where('user_id', auth()->id())->exists() : false,
        ];
    }
}; ?>

<section id="nabidky" class="card space-y-4" aria-labelledby="nabidky-h">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="nabidky-h" class="font-display text-xl font-bold">Nabídky řešení ({{ $offers->count() }})</h2>
        @auth @if (! $mine && auth()->id() !== $need->user_id && in_array($need->status, ['open', 'in_progress'], true))<button wire:click="$toggle('form')" class="btn-accent btn-sm">Nabízím řešení</button>@endif @endauth
    </div>
    @if ($info)<p class="rounded bg-forest-100 px-3 py-2 text-sm font-semibold text-forest" role="status">{{ $info }}</p>@endif
    @if ($error)<p class="text-sm font-semibold text-terra-ink" role="alert">{{ $error }}</p>@endif
    @if ($form)
        <form wire:submit="create" class="space-y-2 rounded-md bg-surface-2 p-4">
            <textarea wire:model="description" rows="3" class="input" placeholder="Co nabízíš, kde, jak to funguje…" aria-label="Popis nabídky"></textarea>
            @error('description')<p class="error">{{ $message }}</p>@enderror
            <input wire:model="price" class="input" placeholder="Orientační cena (nepovinné), např. 500 Kč za výjezd" aria-label="Cena">
            <p class="text-[12px] text-muted">Bez odkazů a kontaktů – zájemcům, kteří souhlasí, můžeš napsat přes platformu.</p>
            <button class="btn-forest btn-sm">Zveřejnit nabídku</button>
        </form>
    @endif
    @forelse ($offers as $o)
        <div class="rounded-md border border-line p-4" wire:key="of{{ $o->id }}">
            <p class="text-sm"><b>{{ $o->user->isDeleted() ? 'Smazaný účet' : $o->user->name }}</b> · {{ local_time($o->created_at)->diffForHumans() }} · <span class="badge-outline">{{ \App\Models\NeedOffer::STATUSES[$o->status] }}</span>
                @if (! $o->moderation_status->isPublic())<span class="badge-terra">{{ $o->moderation_status->label() }}</span>@endif</p>
            <p class="mt-1 whitespace-pre-line">{{ $o->description }}</p>
            @if ($o->price_text)<p class="mt-1 text-sm"><b>Cena:</b> {{ $o->price_text }}</p>@endif
            <div class="mt-2 flex flex-wrap gap-2">
                @if (auth()->id() === $need->user_id && $o->status === 'pending')
                    <button wire:click="decide({{ $o->id }}, true)" class="btn-forest btn-sm">Přijmout</button>
                    <button wire:click="decide({{ $o->id }}, false)" class="btn-ghost btn-sm">Nevyužiju</button>
                @endif
                @if (auth()->id() === $o->user_id)
                    <button wire:click="withdraw({{ $o->id }})" wire:confirm="Stáhnout nabídku?" class="btn-ghost btn-sm">Stáhnout</button>
                @elseif (auth()->check())
                    <livewire:report-button type="need_offer" :id="$o->id" :key="'rp-o-'.$o->id" />
                @endif
            </div>
            @if (auth()->id() === $o->user_id && $o->moderation_status->isPublic())
                <div class="mt-3 space-y-2 rounded bg-surface-2 p-3">
                    <p class="text-sm"><b>Napsat zájemcům</b> · souhlas s kontaktem dalo {{ cz_plural($consenting, 'člověk', 'lidé', 'lidí') }}. Jejich e-maily ani jména neuvidíš.</p>
                    <textarea wire:model="message.{{ $o->id }}" rows="2" class="input" maxlength="1000" aria-label="Zpráva zájemcům"></textarea>
                    <button wire:click="broadcast({{ $o->id }})" class="btn-outline btn-sm">Odeslat přes platformu</button>
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-muted">Zatím nikdo řešení nenabídl. Umíš to? Přidej nabídku.</p>
    @endforelse
</section>
