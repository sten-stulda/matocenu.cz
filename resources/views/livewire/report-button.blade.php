<?php

use App\Domain\Moderation\Actions\ReportContent;
use App\Domain\Moderation\Enums\ReportReason;
use App\Domain\Moderation\Exceptions\ModerationException;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $type;

    #[Locked]
    public int $id;

    public bool $open = false;
    public string $reason = 'spam';
    public string $note = '';
    public ?string $done = null;

    public function submit(ReportContent $report): void
    {
        abort_unless(auth()->check(), 403);
        $this->validate(['reason' => 'required|in:'.implode(',', array_column(ReportReason::cases(), 'value')), 'note' => 'nullable|string|max:500']);
        $key = 'report:'.auth()->id();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('reason', 'Příliš mnoho nahlášení za hodinu.');

            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 3600);
        $target = match ($this->type) {
            'answer' => Answer::findOrFail($this->id),
            'comment' => Comment::findOrFail($this->id),
            'question' => Question::findOrFail($this->id),
            default => abort(404),
        };
        try {
            $report(auth()->user(), $target, ReportReason::from($this->reason), $this->note ?: null);
            $this->done = 'Díky! Moderátor se na to podívá.';
        } catch (ModerationException $e) {
            $this->done = $e->getMessage();
        }
        $this->open = false;
    }
}; ?>

<div class="relative">
    @if ($done)
        <span class="text-[12px] text-muted" role="status">{{ $done }}</span>
    @else
        <button type="button" wire:click="$set('open', true)" class="btn-ghost w-10 px-0 text-muted" aria-label="Nahlásit"><x-icon name="flag" :size="18" /></button>
        @if ($open)
            <div class="fixed inset-0 z-40 grid place-items-center bg-black/40 p-4" wire:click.self="$set('open', false)" @keydown.escape.window="$wire.set('open', false)">
                <form wire:submit="submit" class="card w-full max-w-sm space-y-3" role="dialog" aria-modal="true" aria-labelledby="rp-{{ $type }}-{{ $id }}">
                    <h3 id="rp-{{ $type }}-{{ $id }}" class="font-display text-lg font-bold">Nahlásit příspěvek</h3>
                    <div class="space-y-1.5">
                        @foreach (ReportReason::cases() as $r)
                            <label class="flex items-center gap-2 text-sm"><input type="radio" wire:model="reason" value="{{ $r->value }}" class="accent-forest"> {{ $r->label() }}</label>
                        @endforeach
                    </div>
                    <textarea wire:model="note" rows="2" maxlength="500" class="input" placeholder="Poznámka pro moderátora (nepovinné)" aria-label="Poznámka"></textarea>
                    @error('reason')<p class="error">{{ $message }}</p>@enderror
                    <div class="flex gap-2"><button class="btn-forest">Nahlásit</button><button type="button" wire:click="$set('open', false)" class="btn-ghost">Zrušit</button></div>
                </form>
            </div>
        @endif
    @endif
</div>
