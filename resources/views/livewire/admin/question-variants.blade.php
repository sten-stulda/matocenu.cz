<?php

use App\Domain\AI\Exceptions\AiBudgetExceeded;
use App\Domain\AI\Exceptions\AiInvalidOutput;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\AI\Services\QuestionStylizer;
use App\Models\LanguageStyle;
use App\Models\Question;
use App\Models\QuestionVariant;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $questionId;

    public ?string $error = null;
    /** @var array<int, string> */
    public array $edits = [];

    public function generate(int $styleId, QuestionStylizer $stylizer): void
    {
        $this->authorize('questions.manage');
        $this->error = null;
        try {
            $stylizer->generate(auth()->user(), Question::findOrFail($this->questionId), LanguageStyle::findOrFail($styleId));
        } catch (AiUnavailable|AiBudgetExceeded|AiInvalidOutput $e) {
            $this->error = $e->getMessage();
        }
    }

    public function review(int $id, bool $approve, QuestionStylizer $stylizer): void
    {
        $this->authorize('questions.manage');
        try {
            $stylizer->review(auth()->user(), QuestionVariant::findOrFail($id), $approve, $this->edits[$id] ?? null);
        } catch (AiInvalidOutput $e) {
            $this->error = $e->getMessage();
        }
    }

    public function with(QuestionStylizer $stylizer): array
    {
        $q = Question::findOrFail($this->questionId);

        return [
            'q' => $q,
            'styles' => $stylizer->stylesFor($q),
            'variants' => QuestionVariant::with('style')->where('question_id', $q->id)->where('question_version', $q->version)->latest()->get()->keyBy('language_style_id'),
        ];
    }
}; ?>

<div class="card space-y-3">
    <h2 class="font-display text-lg font-bold">Stylizované varianty (verze {{ $q->version }})</h2>
    <p class="text-xs text-muted">Generují se jednou pro danou verzi otázky, stylu a promptu. Veřejně se zobrazí jen schválené.@if ($q->is_sensitive) U citlivé otázky jsou k dispozici jen neutrální styly.@endif</p>
    @if ($error)<p class="text-sm font-semibold text-terra-ink" role="alert">{{ $error }}</p>@endif
    <ul class="space-y-3">
        @foreach ($styles as $s)
            @php $v = $variants->get($s->id); @endphp
            <li class="text-sm" wire:key="qv{{ $s->id }}">
                <div class="flex items-center gap-2"><b>{{ $s->name }}</b>
                    @if ($v)<span class="{{ ['approved' => 'badge-forest', 'pending' => 'badge-mustard', 'rejected' => 'badge-terra'][$v->status] }}">{{ ['approved' => 'schváleno', 'pending' => 'čeká', 'rejected' => 'zamítnuto'][$v->status] }}</span>
                    @else<button wire:click="generate({{ $s->id }})" class="btn-outline btn-sm ml-auto" wire:loading.attr="disabled" wire:target="generate({{ $s->id }})"><span wire:loading.remove wire:target="generate({{ $s->id }})">Vygenerovat</span><span wire:loading wire:target="generate({{ $s->id }})">Vaříme…</span></button>@endif
                </div>
                @if ($v)
                    <input wire:model="edits.{{ $v->id }}" class="input mt-1 py-1.5 text-sm" value="{{ $v->text }}" placeholder="{{ $v->text }}" aria-label="Upravit variantu">
                    <p class="mt-1 italic">„{{ $v->text }}“</p>
                    <div class="mt-1 flex gap-2">
                        @if ($v->status !== 'approved')<button wire:click="review({{ $v->id }}, true)" class="btn-forest btn-sm">Schválit</button>@endif
                        @if ($v->status !== 'rejected')<button wire:click="review({{ $v->id }}, false)" class="btn-danger btn-sm">Zamítnout</button>@endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
