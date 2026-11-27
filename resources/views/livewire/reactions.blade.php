<?php

use App\Domain\Reactions\Actions\ToggleReaction;
use App\Domain\Reactions\Exceptions\ReactionException;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Reaction;
use App\Models\ReactionType;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $type = 'answer';

    #[Locked]
    public int $id;

    #[Locked]
    public ?int $ownerId = null;

    /** @var array<string, int> */
    public array $counts = [];

    public ?string $mine = null;

    public ?string $error = null;

    /** @param array{counts: array<string,int>, mine: ?string}|null $preloaded */
    public function mount(?array $preloaded = null): void
    {
        abort_unless(in_array($this->type, ['answer', 'comment'], true), 404);
        if ($preloaded) {
            $this->counts = $preloaded['counts'];
            $this->mine = $preloaded['mine'];
        } else {
            $this->load();
        }
    }

    private function load(): void
    {
        $rows = Reaction::query()->join('reaction_types', 'reaction_types.id', '=', 'reactions.reaction_type_id')
            ->where('reactable_type', $this->type)->where('reactable_id', $this->id)
            ->selectRaw('reaction_types.slug, count(*) as n')->groupBy('reaction_types.slug')->pluck('n', 'slug');
        $this->counts = $rows->map(fn ($n) => (int) $n)->all();
        $this->mine = auth()->check() ? Reaction::query()->join('reaction_types', 'reaction_types.id', '=', 'reactions.reaction_type_id')
            ->where('reactable_type', $this->type)->where('reactable_id', $this->id)->where('user_id', auth()->id())->value('reaction_types.slug') : null;
    }

    public function react(string $slug, ToggleReaction $toggle): void
    {
        $this->error = null;
        if (! auth()->check()) {
            $this->redirectRoute('login');

            return;
        }
        $target = $this->type === 'answer' ? Answer::findOrFail($this->id) : Comment::findOrFail($this->id);
        $key = 'react:'.auth()->id();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 60)) {
            $this->error = 'Pomaleji, sousede. Zkus to za chvilku.';

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        try {
            $toggle(auth()->user(), $target, $slug);
        } catch (ReactionException $e) {
            $this->error = $e->getMessage();
        }
        $this->load();
    }

    public function with(): array
    {
        return ['types' => ReactionType::active()];
    }
}; ?>

<div class="relative flex flex-wrap items-center gap-1.5" x-data="{ open: false }" @keydown.escape="open = false" @click.outside="open = false">
    @php
        $primary = $types->first();
        $isOwn = auth()->id() !== null && auth()->id() === $ownerId;
        $shown = $types->filter(fn ($t) => ($counts[$t->slug] ?? 0) > 0 || $t->slug === $primary?->slug)->take(2);
    @endphp
    @foreach ($shown as $t)
        <button type="button" wire:click="react('{{ $t->slug }}')" @disabled($isOwn)
                aria-pressed="{{ $mine === $t->slug ? 'true' : 'false' }}" class="chip" wire:loading.attr="disabled"
                title="{{ $isOwn ? 'Vlastní odpověď hodnotit nelze' : $t->label }}">
            {{ $t->label }} <span class="tabular-nums">{{ $counts[$t->slug] ?? 0 }}</span>
        </button>
    @endforeach
    @unless ($isOwn)
        <button type="button" class="chip" @click="open = !open" :aria-expanded="open" aria-haspopup="true">+ reakce</button>
        <div x-cloak x-show="open" x-transition class="absolute bottom-full left-0 z-20 mb-2 flex w-[290px] flex-wrap gap-1.5 rounded-[10px] border border-line bg-surface p-2 shadow-[var(--shadow-pop)]" role="menu">
            @foreach ($types as $t)
                <button type="button" role="menuitem" wire:click="react('{{ $t->slug }}')" @click="open = false"
                        aria-pressed="{{ $mine === $t->slug ? 'true' : 'false' }}" class="chip">
                    <x-icon :name="$t->icon" :size="16" /> {{ $t->label }}
                </button>
            @endforeach
        </div>
    @endunless
    @if ($error)<p class="w-full text-[13px] font-semibold text-terra-ink dark:text-terra" role="alert">{{ $error }}</p>@endif
</div>
