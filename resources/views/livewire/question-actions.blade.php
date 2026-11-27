<?php

use App\Domain\Social\Actions\SocialActions;
use App\Models\Bookmark;
use App\Models\Follow;
use App\Models\Question;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $questionId;

    public bool $following = false;
    public bool $bookmarked = false;

    public function mount(): void
    {
        if ($id = auth()->id()) {
            $this->following = Follow::where(['user_id' => $id, 'followable_type' => 'question', 'followable_id' => $this->questionId])->exists();
            $this->bookmarked = Bookmark::where(['user_id' => $id, 'bookmarkable_type' => 'question', 'bookmarkable_id' => $this->questionId])->exists();
        }
    }

    public function follow(SocialActions $social): void
    {
        abort_unless(auth()->check(), 403);
        $this->following = $social->toggleFollow(auth()->user(), Question::findOrFail($this->questionId));
    }

    public function bookmark(SocialActions $social): void
    {
        abort_unless(auth()->check(), 403);
        $this->bookmarked = $social->toggleBookmark(auth()->user(), Question::findOrFail($this->questionId));
    }
}; ?>

<div class="flex flex-wrap gap-2">
    @auth
        <button wire:click="follow" class="chip" aria-pressed="{{ $following ? 'true' : 'false' }}"><x-icon name="bell" :size="16" /> {{ $following ? 'Sleduji' : 'Sledovat' }}</button>
        <button wire:click="bookmark" class="chip" aria-pressed="{{ $bookmarked ? 'true' : 'false' }}"><x-icon name="bookmark" :size="16" /> {{ $bookmarked ? 'V oblíbených' : 'Do oblíbených' }}</button>
    @endauth
</div>
