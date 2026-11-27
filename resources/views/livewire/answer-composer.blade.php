<?php

use App\Domain\AI\Exceptions\AiBudgetExceeded;
use App\Domain\AI\Exceptions\AiInvalidOutput;
use App\Domain\AI\Exceptions\AiStyleNotAllowed;
use App\Domain\AI\Exceptions\AiUnavailable;
use App\Domain\AI\Services\AiStatus;
use App\Domain\AI\Services\AnswerStylizer;
use App\Domain\Answers\Actions\DeleteAnswer;
use App\Domain\Answers\Actions\SubmitAnswer;
use App\Domain\Answers\Actions\UpdateAnswer;
use App\Domain\Answers\DTO\AnswerData;
use App\Domain\Answers\Exceptions\AnswerException;
use App\Domain\Moderation\Services\ContentChecker;
use App\Models\Answer;
use App\Models\LanguageStyle;
use App\Models\Question;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * 1 napiš → 2 vyber styl → 3 AI vaří → 4 vyber variantu → 5 potvrď → zveřejněno.
 * Without AI (unavailable, budget, sensitive topic) the author publishes the original.
 */
new class extends Component {
    #[Locked]
    public int $questionId;

    public bool $dark = false;

    public string $step = 'write'; // write | choose | confirm
    public string $text = '';
    public ?string $styleSlug = null;
    public bool $keepOriginal = false;
    /** @var list<array{id: int, text: string}> */
    public array $variants = [];
    #[Locked]
    public ?string $generatedStyle = null;
    public ?int $chosenVariant = null; // null = original
    public bool $showOriginal = true;
    public bool $anonymous = false;
    public ?int $optionId = null;
    public ?string $numeric = null;
    public bool $editing = false;
    public ?string $hint = null;
    public ?string $aiError = null;
    public ?string $flash = null;
    public int $regenerationsLeft = 0;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $prefs = $user->preferences();
            $this->anonymous = (bool) $prefs->anonymous_by_default;
            $this->showOriginal = (bool) $prefs->show_original_by_default;
            $fav = $user->favorite_style_id ? LanguageStyle::find($user->favorite_style_id) : null;
            $this->styleSlug = $fav && $this->styles->contains('slug', $fav->slug) ? $fav->slug : null;
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
        return auth()->check() ? Answer::with('style')->where('question_id', $this->questionId)->where('user_id', auth()->id())->latest()->first() : null;
    }

    #[Computed]
    public function aiAvailable(): bool
    {
        return app(AiStatus::class)->availableFor(auth()->user());
    }

    /** Styles the author may pick for this question (sensitive topics: neutral styles only, unless opted in). */
    #[Computed]
    public function styles()
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }
        $stylizer = app(AnswerStylizer::class);

        return LanguageStyle::active()->filter(fn ($s) => ($s->is_random && ! $this->question->is_sensitive) || $stylizer->allowedFor($s, $this->question, $user))->values();
    }

    public function updatedText(ContentChecker $checker): void
    {
        $this->hint = $checker->hint($this->text);
    }

    public function chooseStyle(string $slug): void
    {
        $this->styleSlug = $slug;
        $this->keepOriginal = false;
    }

    /** Step 3: AI generates variants (cache first). */
    public function stylize(AnswerStylizer $stylizer, bool $regenerate = false): void
    {
        $this->aiError = null;
        $this->resetErrorBag();
        abort_unless(auth()->check(), 403);
        $style = $this->styles->firstWhere('slug', $this->styleSlug);
        if (! $style) {
            $this->aiError = 'Vyber jazykový styl, nebo zveřejni originál.';

            return;
        }
        if (RateLimiter::tooManyAttempts('ai:'.auth()->id(), 6)) {
            $this->aiError = 'Pomaleji, sousede – zkus to za minutku.';

            return;
        }
        RateLimiter::hit('ai:'.auth()->id(), 60);
        try {
            $g = $stylizer->generate(auth()->user(), $this->question, $this->text, $style, $regenerate);
            $this->variants = $g->variants->map(fn ($v) => ['id' => $v->id, 'text' => $v->text])->all();
            $this->generatedStyle = $g->style?->name;
            $this->chosenVariant = $this->variants[0]['id'] ?? null;
            $this->regenerationsLeft = $stylizer->regenerationsLeft(auth()->user(), $this->question, $this->text, $g->style ?? $style);
            $this->step = 'choose';
        } catch (AnswerException $e) {
            $this->addError('text', $e->getMessage());
        } catch (AiStyleNotAllowed|AiBudgetExceeded|AiUnavailable $e) {
            $this->aiError = $e->getMessage();
        } catch (AiInvalidOutput) {
            $this->aiError = 'Tentokrát se hláška nepovedla tak, aby zachovala tvůj význam. Zkus jiný styl, nebo zveřejni originál.';
        }
    }

    public function regenerate(AnswerStylizer $stylizer): void
    {
        $this->stylize($stylizer, true);
    }

    public function toConfirm(): void
    {
        $this->step = 'confirm';
    }

    public function back(): void
    {
        $this->step = $this->step === 'confirm' && $this->variants ? 'choose' : 'write';
    }

    private function data(): AnswerData
    {
        return new AnswerData(
            originalText: $this->text,
            isAnonymous: $this->anonymous,
            showOriginal: $this->showOriginal,
            optionId: $this->optionId,
            numericValue: $this->numeric,
            variantId: $this->step === 'confirm' && ! $this->keepOriginal ? $this->chosenVariant : null,
        );
    }

    public function publish(SubmitAnswer $submit, UpdateAnswer $update): void
    {
        abort_unless(auth()->check(), 403);
        if (RateLimiter::tooManyAttempts('answer:'.auth()->id(), 10)) {
            $this->addError('text', 'Moc odpovědí za krátkou dobu. Dej si chvilku.');

            return;
        }
        RateLimiter::hit('answer:'.auth()->id(), 60);
        try {
            $answer = $this->editing ? $update(auth()->user(), $this->mine, $this->data()) : $submit(auth()->user(), $this->question, $this->data());
            $this->reset('text', 'hint', 'variants', 'chosenVariant', 'generatedStyle', 'aiError', 'keepOriginal', 'editing');
            $this->step = 'write';
            unset($this->mine);
            $this->flash = $answer->moderation_status->isPublic()
                ? 'Zveřejněno! Teď je řada na ostatních.'
                : 'Odpověď čeká na kontrolu moderátora (obsahuje něco, co musí posoudit člověk).';
            $this->dispatch('answer-published', id: $answer->id);
        } catch (AnswerException $e) {
            $this->addError('text', $e->getMessage());
            $this->step = 'write';
        }
    }

    public function startEdit(): void
    {
        abort_unless($this->mine, 404);
        $this->editing = true;
        $this->step = 'write';
        $this->text = $this->mine->original_text;
        $this->anonymous = $this->mine->is_anonymous;
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'text', 'variants', 'step');
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
    $chosenText = collect($variants)->firstWhere('id', $chosenVariant)['text'] ?? null;
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
        @if ($mine->is_stylized)<p class="text-[12px] text-muted">Stylizováno AI · {{ $mine->style?->name }} · originál: „{{ $mine->original_text }}“{{ $mine->show_original ? '' : ' (vidíš jen ty)' }}</p>@endif
        @if (! $mine->moderation_status->isPublic())<p class="mt-1 badge-terra">{{ $mine->moderation_status->label() }}</p>@endif
        <div class="mt-3 flex flex-wrap gap-2">
            <button wire:click="startEdit" class="btn-outline btn-sm">Upravit</button>
            <button wire:click="remove" wire:confirm="Opravdu smazat odpověď?" class="btn-ghost btn-sm">Smazat</button>
            <a href="{{ $mine->url() }}" class="btn-ghost btn-sm">Zobrazit</a>
        </div>

    {{-- STEP 1 + 2: write and choose a style --}}
    @elseif ($step === 'write')
        <div x-data="charCounter({{ $max }})" x-init="text = $wire.text" class="space-y-3">
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
                <span class="text-muted">Stačí jedno slovo.</span>
                @if ($q->is_sensitive)<span class="badge-terra">Citlivé téma – bez humoru</span>@endif
            </div>
            @if ($hint)<p class="text-[13px] font-semibold text-terra-ink" role="alert">{{ $hint }}</p>@endif
            @error('text')<p class="error" role="alert">{{ $message }}</p>@enderror

            @if ($this->aiAvailable && $this->styles->isNotEmpty())
                <div class="space-y-2">
                    <p class="text-sm font-semibold">Jazykový styl <span class="font-normal text-muted">(nepovinné)</span></p>
                    <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Jazykový styl odpovědi">
                        @foreach ($this->styles as $s)
                            <button type="button" role="radio" aria-checked="{{ $styleSlug === $s->slug && ! $keepOriginal ? 'true' : 'false' }}" wire:click="chooseStyle('{{ $s->slug }}')"
                                    @class(['chip', 'chip-active' => $styleSlug === $s->slug && ! $keepOriginal])>{{ $s->name }}</button>
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="keepOriginal" class="h-4 w-4 accent-forest"> Ponechat originál bez humoru</label>
                </div>
            @elseif (! $this->aiAvailable)
                <p class="text-[13px] text-muted">{{ app(\App\Domain\AI\Services\AiStatus::class)->unavailableReason() }}</p>
            @endif
            @if ($aiError)<p class="rounded bg-terra-100 px-3 py-2 text-sm font-semibold text-terra-ink" role="alert">{{ $aiError }}</p>@endif

            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($editing)<button type="button" wire:click="cancelEdit" class="btn-ghost">Zrušit</button>@endif
                <label class="mr-auto flex items-center gap-2 text-sm"><input type="checkbox" wire:model="anonymous" class="h-4 w-4 accent-forest"> Anonymně</label>
                <button type="button" wire:click="publish" class="btn-outline" :disabled="over || count === 0" wire:loading.attr="disabled">{{ $editing ? 'Uložit originál' : 'Zveřejnit originál' }}</button>
                @if ($this->aiAvailable && $this->styles->isNotEmpty() && ! $keepOriginal)
                    <button type="button" wire:click="stylize" class="btn-forest" :disabled="over || count === 0 || {{ $styleSlug ? 'false' : 'true' }}" wire:loading.attr="disabled" wire:target="stylize">
                        <span wire:loading.remove wire:target="stylize"><x-icon name="sparkle" :size="16" class="inline" /> Odpovědět s humorem</span>
                        <span wire:loading wire:target="stylize">Přisypáváme špetku humoru…</span>
                    </button>
                @endif
            </div>
            <p class="font-hand text-lg text-forest dark:text-primary">Krátké odpovědi bývají nejsilnější.</p>
        </div>

    {{-- STEP 4: choose a variant --}}
    @elseif ($step === 'choose')
        <div class="space-y-3">
            <p class="text-sm font-bold text-terra-ink dark:text-mustard">VYBER VARIANTU · {{ $generatedStyle }}</p>
            @if (app(\App\Domain\AI\Services\AiStatus::class)->isTestMode())<p class="badge-dashed">Testovací režim – žádná skutečná AI, jen označené kopie originálu</p>@endif
            <div class="space-y-2" role="radiogroup" aria-label="Varianty">
                @foreach ($variants as $v)
                    <label @class(['flex cursor-pointer items-start gap-3 rounded-md border-[1.5px] p-3', 'border-forest bg-forest-100/40 dark:border-primary dark:bg-surface-2' => $chosenVariant === $v['id'] && ! $keepOriginal, 'border-line' => ! ($chosenVariant === $v['id'] && ! $keepOriginal)])>
                        <input type="radio" wire:model.live="chosenVariant" value="{{ $v['id'] }}" wire:click="$set('keepOriginal', false)" class="mt-1 accent-forest"> „{{ $v['text'] }}“
                    </label>
                @endforeach
                <label @class(['flex cursor-pointer items-start gap-3 rounded-md border-[1.5px] border-dashed p-3', 'border-forest' => $keepOriginal, 'border-line' => ! $keepOriginal])>
                    <input type="radio" name="keep" wire:click="$set('keepOriginal', true)" @checked($keepOriginal) class="mt-1 accent-forest"> Ponechat originál: „{{ $text }}“
                </label>
            </div>
            @if ($regenerationsLeft > 0)
                <button type="button" wire:click="regenerate" class="link text-sm" wire:loading.attr="disabled" wire:target="regenerate">
                    <span wire:loading.remove wire:target="regenerate">Zkusit jiné varianty (zbývá {{ $regenerationsLeft }}×)</span>
                    <span wire:loading wire:target="regenerate">Hledáme tu správnú hlášku…</span>
                </button>
            @endif
            @if ($aiError)<p class="text-sm font-semibold text-terra-ink" role="alert">{{ $aiError }}</p>@endif
            <div class="flex justify-between gap-2">
                <button type="button" wire:click="back" class="btn-ghost">Zpět</button>
                <button type="button" wire:click="toConfirm" class="btn-forest">Pokračovat</button>
            </div>
        </div>

    {{-- STEP 5: confirm --}}
    @else
        <div class="space-y-3">
            <p class="text-sm font-bold text-terra-ink dark:text-mustard">POTVRĎ</p>
            <div class="rounded-md bg-surface-2 p-4">
                <p class="text-[12px] font-semibold">Náhled tvé karty</p>
                <p class="mt-1 font-display text-lg">„{{ $keepOriginal || ! $chosenText ? $text : $chosenText }}“</p>
                @if (! $keepOriginal && $chosenText)<p class="mt-1 text-[12px] text-muted">Stylizováno AI · {{ $generatedStyle }} · originál: „{{ $text }}“</p>@endif
            </div>
            @if (! $keepOriginal && $chosenText)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="showOriginal" class="h-4 w-4 accent-forest"> Ostatní smí vidět můj originál</label>
            @endif
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="anonymous" class="h-4 w-4 accent-forest"> Zveřejnit anonymně</label>
            @error('text')<p class="error" role="alert">{{ $message }}</p>@enderror
            <div class="flex justify-between gap-2">
                <button type="button" wire:click="back" class="btn-ghost">Zpět</button>
                <button type="button" wire:click="publish" class="btn-forest" wire:loading.attr="disabled">Zveřejnit odpověď</button>
            </div>
            <p class="text-[12px] text-muted">AI změnila jen formu. Tvoje myšlenka zůstává tvoje – a statistiky počítáme z originálu.</p>
        </div>
    @endif
</div>
