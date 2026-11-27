<?php

use App\Domain\Moderation\Actions\AppealDecision;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Models\ModerationAction;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Moderace mého obsahu')] class extends Component {
    /** @var array<int, string> */
    public array $message = [];

    public function appeal(int $actionId, AppealDecision $appeals): void
    {
        $this->validate(["message.$actionId" => 'required|string|min:10|max:1000'], [], ["message.$actionId" => 'zdůvodnění']);
        try {
            $appeals->file(auth()->user(), ModerationAction::findOrFail($actionId), $this->message[$actionId]);
            session()->flash('status', 'Odvolání odesláno. Posoudí ho jiný moderátor.');
        } catch (ModerationException $e) {
            $this->addError("message.$actionId", $e->getMessage());
        }
    }

    public function with(): array
    {
        $uid = auth()->id();
        $mine = fn ($table, $col) => DB::table($table)->where($col, $uid)->select('id');
        $actions = ModerationAction::query()->with(['moderatable', 'appeals'])
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('moderatable_type', 'answer')->whereIn('moderatable_id', $mine('answers', 'user_id')))
                ->orWhere(fn ($w) => $w->where('moderatable_type', 'comment')->whereIn('moderatable_id', $mine('comments', 'user_id')))
                ->orWhere(fn ($w) => $w->where('moderatable_type', 'question')->whereIn('moderatable_id', $mine('questions', 'author_id'))))
            ->latest('created_at')->limit(50)->get();

        return ['actions' => $actions];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    @include('pages.account.nav', ['active' => 'moderation'])
    <h1 class="font-display text-2xl font-bold">Moderace mého obsahu</h1>
    <p class="text-sm text-muted">Historie rozhodnutí o tvých příspěvcích. Proti skrytí nebo zamítnutí se můžeš jednou odvolat – posoudí to jiný moderátor.</p>
    @forelse ($actions as $a)
        <div class="card space-y-2">
            <p class="text-sm"><b>{{ $a->label() }}</b> · {{ local_time($a->created_at)->format('j. n. Y H:i') }} · stav: {{ \App\Domain\Moderation\Enums\ModerationStatus::tryFrom($a->to_status)?->label() }}</p>
            <p class="font-display">„{{ $a->moderatable?->moderationExcerpt() ?? 'smazaný příspěvek' }}“</p>
            @if ($a->reason)<p class="text-sm text-muted">Důvod: {{ $a->reason }}</p>@endif
            @php $appeal = $a->appeals->firstWhere('user_id', auth()->id()); @endphp
            @if ($appeal)
                <p class="text-sm"><span class="badge-mustard">Odvolání: {{ ['open' => 'čeká', 'upheld' => 'zamítnuto', 'overturned' => 'vyhověno'][$appeal->status] }}</span> @if ($appeal->decision_note) {{ $appeal->decision_note }} @endif</p>
            @elseif (in_array($a->to_status, ['hidden', 'rejected', 'under_review'], true) && $a->moderatable)
                <div class="space-y-2">
                    <textarea wire:model="message.{{ $a->id }}" rows="2" class="input" placeholder="Proč si myslíš, že rozhodnutí není správné?" aria-label="Zdůvodnění odvolání"></textarea>
                    @error("message.$a->id")<p class="error">{{ $message }}</p>@enderror
                    <button wire:click="appeal({{ $a->id }})" class="btn-outline btn-sm">Odvolat se</button>
                </div>
            @endif
        </div>
    @empty
        <x-empty title="Tvůj obsah zatím nikdo nemoderoval. Tak to má být." />
    @endforelse
</div>
