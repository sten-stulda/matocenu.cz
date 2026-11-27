<?php

use App\Domain\Answers\Actions\DeleteAnswer;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\Actions\UpdateAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Moderation\Services\ContentChecker;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Write → (Etapa C: choose style → AI → choose variant) → confirm → publish.
 * Without AI the author publishes the original.
 */
new class extends Component {
    #[Locked]
    public int $questionId;

    public bool $dark = false;

    public string $text = '';
    public bool $anonymous = false;
    public ?int $optionId = null;
    public ?string $numeric = null;
    public bool $editing = false;
    public ?string $hint = null;
    public ?string $flash = null;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->anonymous = (bool) $user->preferences()->anonymous_by_default;
        }
    }

    #[Computed]
    public function question(): Question
    {
        return Question::with('options')->findOrFail($this->questionId);
    }

    #[Computed]
    public function mine(): ?Answer
    {
        return auth()->check() ? Answer::where('question_id', $this->questionId)->where('user_id', auth()->id())->latest()->first() : null;
    }

    public function updatedText(ContentChecker $checker): void
    {
        $this->hint = $checker->hint($this->text);
    }

    private function data(): AnswerData
    {
        return new AnswerData(originalText: $this->text, isAnonymous: $this->anonymous, optionId: $this->optionId, numericValue: $this->numeric);
    }

    public function publish(SubmitAnswer $submit): void
    {
        abort_unless(auth()->check(), 403);
        $key = 'answer:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('text', 'Moc odpovědí za krátkou dobu. Dej si chvilku.');

            return;
        }
        RateLimiter::hit($key, 60);
        try {
            $answer = $submit(auth()->user(), $this->question, $this->data());
            $this->reset('text', 'hint');
            unset($this->mine);
            $this->flash = $answer->moderation_status->isPublic()
                ? 'Zveřejněno! Teď je řada na ostatních.'
                : 'Odpověď čeká na kontrolu moderátora (obsahuje něco, co musí posoudit člověk).';
            $this->dispatch('answer-published', id: $answer->id);
        } catch (AnswerException $e) {
            $this->addError('text', $e->getMessage());
        }
    }

    public function startEdit(): void
    {
        $mine = $this->mine;
        abort_unless($mine, 404);
        $this->editing = true;
        $this->text = $mine->original_text;
        $this->anonymous = $mine->is_anonymous;
    }

    public function saveEdit(UpdateAnswer $update): void
    {
        try {
            $update(auth()->user(), $this->mine, $this->data());
            $this->editing = false;
            $this->reset('text', 'hint');
            unset($this->mine);
            $this->flash = 'Odpověď upravena.';
        } catch (AnswerException $e) {
            $this->addError('text', $e->getMessage());
        }
    }

    public function remove(DeleteAnswer $delete): void
    {
        $delete(auth()->user(), $this->mine);
        unset($this->mine);
        $this->flash = 'Odpověď smazána.';
    }
}; ?>

@php
    $q = $this->question;
    $max = (int) setting('answers.max_length');
    $mine = $this->mine;
@endphp
<div @class(["rounded-[10px] p-4 sm:p-5", "bg-cream text-charcoal dark:bg-surface dark:text-ink" => $dark, "border border-line bg-surface text-ink shadow-[var(--shadow-card)]" => ! $dark]) id="odpovedet">
    @if ($flash)<p class="mb-3 rounded bg-forest-100 px-3 py-2 text-sm font-semibold text-forest" role="status">{{ $flash }}</p>@endif

    @if (! $q->acceptsAnswers())
        <p class="font-semibold">Na tuhle otázku už se odpovídat nedá.</p>
    @elseguest
        <p class="font-semibold">Tvoje odpověď</p>
        <p class="mt-1 text-sm text-muted">Odpovídat můžou registrovaní – přezdívka stačí, e-mail nikdo neuvidí.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="{{ route('register') }}" class="btn-forest">Založit účet a odpovědět</a>
            <a href="{{ route('login') }}" class="btn-outline">Přihlásit se</a>
        </div>
    @elseif (setting('answers.require_verified_email') && ! auth()->user()->hasVerifiedEmail())
        <p class="font-semibold">Ještě ověř e-mail</p>
        <p class="mt-1 text-sm">Poslali jsme ti odkaz. Po ověření můžeš odpovídat. <a href="{{ route('verification.notice') }}" class="link">Poslat znovu</a></p>
    @elseif ($mine && ! $editing)
        <p class="text-sm font-semibold">Tvoje odpověď</p>
        <p class="mt-1 font-display text-lg">„{{ $mine->display_text }}“</p>
        @if (! $mine->moderation_status->isPublic())<p class="mt-1 badge-terra">{{ $mine->moderation_status->label() }}</p>@endif
        <div class="mt-3 flex flex-wrap gap-2">
            <button wire:click="startEdit" class="btn-outline btn-sm">Upravit</button>
            <button wire:click="remove" wire:confirm="Opravdu smazat odpověď?" class="btn-ghost btn-sm">Smazat</button>
            <a href="{{ $mine->url() }}" class="btn-ghost btn-sm">Zobrazit</a>
        </div>
    @else
        <form wire:submit="{{ $editing ? 'saveEdit' : 'publish' }}" x-data="charCounter({{ $max }})" x-init="text = $wire.text" class="space-y-3">
            <label for="odpoved-{{ $q->id }}" class="text-sm font-semibold">Tvoje odpověď</label>
            @if ($q->type->hasOptions() && ! $editing)
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Možnosti">
                    @foreach ($q->options as $o)
                        <label class="chip cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary has-[:checked]:text-on-primary">
                            <input type="radio" wire:model="optionId" value="{{ $o->id }}" class="sr-only"> {{ $o->label }}
                        </label>
                    @endforeach
                </div>
            @elseif ($q->type->value === 'numeric' && ! $editing)
                <input type="text" inputmode="decimal" wire:model="numeric" class="input max-w-[200px]" placeholder="Číslo" aria-label="Číselná odpověď">
            @endif
            <textarea id="odpoved-{{ $q->id }}" wire:model.live.debounce.600ms="text" x-model="text" rows="2"
                      class="input min-h-[72px] text-[17px] text-charcoal dark:text-ink" :class="over && 'input-error'"
                      placeholder="{{ $q->type->hasOptions() ? 'Proč? Napiš to po svém…' : 'Napiš to po svém, klidně jedním slovem…' }}"
                      aria-describedby="pocitadlo-{{ $q->id }}"></textarea>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span id="pocitadlo-{{ $q->id }}" class="font-semibold tabular-nums" :class="over ? 'text-terra-ink' : 'text-forest dark:text-primary'" aria-live="polite"><span x-text="count">0</span>/{{ $max }}</span>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="anonymous" class="h-4 w-4 accent-forest"> Zveřejnit anonymně</label>
                @if ($q->is_sensitive)<span class="badge-terra">Citlivé téma – bez humoru</span>@endif
            </div>
            @if ($hint)<p class="text-[13px] font-semibold text-terra-ink" role="alert">{{ $hint }}</p>@endif
            @error('text')<p class="error" role="alert">{{ $message }}</p>@enderror
            @error('optionId')<p class="error">{{ $message }}</p>@enderror
            <div class="flex flex-wrap justify-end gap-2">
                @if ($editing)<button type="button" wire:click="$set('editing', false)" class="btn-ghost">Zrušit</button>@endif
                <button class="btn-forest" :disabled="over || count === 0" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="publish,saveEdit">{{ $editing ? 'Uložit úpravu' : 'Zveřejnit odpověď' }}</span>
                    <span wire:loading wire:target="publish,saveEdit">Vaříme odpověď…</span>
                </button>
            </div>
            <p class="font-hand text-lg text-forest dark:text-primary">Krátké odpovědi bývají nejsilnější.</p>
        </form>
    @endif
</div>
