<?php

use App\Domain\Comments\Actions\CommentActions;
use App\Domain\Comments\Exceptions\CommentException;
use App\Models\Answer;
use App\Models\Comment;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public string $type;

    #[Locked]
    public int $id;

    public string $body = '';
    public ?int $replyTo = null;
    public ?int $editing = null;
    public string $editBody = '';
    public int $limit = 30;

    private function target(): Question|Answer|\App\Models\Need
    {
        return match ($this->type) {
            'question' => Question::findOrFail($this->id),
            'need' => \App\Models\Need::findOrFail($this->id),
            'answer' => Answer::findOrFail($this->id),
            default => abort(404),
        };
    }

    #[Computed]
    public function comments()
    {
        $viewer = auth()->user();
        $all = Comment::query()->with('user')
            ->where('commentable_type', $this->type)->where('commentable_id', $this->id)
            ->where(fn ($q) => $q->publiclyVisible()->when($viewer, fn ($w) => $w->orWhere('user_id', $viewer->id)))
            ->when($viewer, fn ($q) => $q->whereNotIn('user_id', DB::table('user_blocks')->where('blocker_id', $viewer->id)->select('blocked_id')))
            ->orderBy('created_at')->limit(300)->get();

        return ['roots' => $all->whereNull('parent_id')->take($this->limit), 'children' => $all->whereNotNull('parent_id')->groupBy('parent_id'), 'total' => $all->count()];
    }

    public function post(CommentActions $actions): void
    {
        abort_unless(auth()->check(), 403);
        if (RateLimiter::tooManyAttempts('comment:'.auth()->id(), 8)) {
            $this->addError('body', 'Moc komentářů za krátkou dobu. Dej si chvilku.');

            return;
        }
        RateLimiter::hit('comment:'.auth()->id(), 60);
        try {
            $parent = $this->replyTo ? Comment::findOrFail($this->replyTo) : null;
            $c = $actions->add(auth()->user(), $this->target(), $this->body, $parent);
            $this->reset('body', 'replyTo');
            unset($this->comments);
            if ($c->moderation_status->value !== 'approved') {
                session()->flash('comment-status', 'Komentář čeká na kontrolu moderátora.');
            }
        } catch (CommentException $e) {
            $this->addError('body', $e->getMessage());
        }
    }

    public function startEdit(int $id): void
    {
        $c = Comment::findOrFail($id);
        $this->authorize('update', $c);
        $this->editing = $id;
        $this->editBody = $c->body;
    }

    public function saveEdit(CommentActions $actions): void
    {
        $c = Comment::findOrFail($this->editing);
        $this->authorize('update', $c);
        try {
            $actions->edit(auth()->user(), $c, $this->editBody);
            $this->reset('editing', 'editBody');
            unset($this->comments);
        } catch (CommentException $e) {
            $this->addError('editBody', $e->getMessage());
        }
    }

    public function remove(int $id, CommentActions $actions): void
    {
        $c = Comment::findOrFail($id);
        $this->authorize('delete', $c);
        $actions->delete(auth()->user(), $c);
        unset($this->comments);
    }
}; ?>

<section id="komentare" aria-labelledby="komentare-h-{{ $type }}-{{ $id }}" class="space-y-4">
    <h2 id="komentare-h-{{ $type }}-{{ $id }}" class="font-display text-xl font-bold">Komentáře ({{ $this->comments['total'] }})</h2>
    @if (session('comment-status'))<p class="text-sm font-semibold text-terra-ink" role="status">{{ session('comment-status') }}</p>@endif

    @forelse ($this->comments['roots'] as $c)
        @include('livewire.partials.comment', ['c' => $c, 'children' => $this->comments['children']])
    @empty
        <p class="text-sm text-muted">Zatím bez komentářů.</p>
    @endforelse

    @auth
        @if (auth()->user()->hasVerifiedEmail())
            <form wire:submit="post" class="space-y-2">
                @if ($replyTo)<p class="text-[13px] text-muted">Odpovídáš na komentář · <button type="button" wire:click="$set('replyTo', null)" class="underline">zrušit</button></p>@endif
                <textarea wire:model="body" rows="2" maxlength="{{ setting('comments.max_length') }}" class="input" placeholder="Napiš komentář…" aria-label="Komentář"></textarea>
                @error('body')<p class="error">{{ $message }}</p>@enderror
                <button class="btn-forest btn-sm" wire:loading.attr="disabled">Odeslat</button>
            </form>
        @else
            <p class="text-sm text-muted">Komentovat můžeš po <a href="{{ route('verification.notice') }}" class="link">ověření e-mailu</a>.</p>
        @endif
    @else
        <p class="text-sm text-muted"><a href="{{ route('login') }}" class="link">Přihlas se</a> a přidej komentář.</p>
    @endauth
</section>
