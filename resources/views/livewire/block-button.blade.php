<?php

use App\Domain\Social\Actions\SocialActions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $userId;

    public bool $blocked = false;

    public function mount(): void
    {
        $this->blocked = DB::table('user_blocks')->where('blocker_id', auth()->id())->where('blocked_id', $this->userId)->exists();
    }

    public function toggle(SocialActions $social): void
    {
        abort_unless(auth()->check(), 403);
        $this->blocked = $social->toggleBlock(auth()->user(), User::findOrFail($this->userId));
    }
}; ?>

<div>
    <button wire:click="toggle" @if (! $blocked) wire:confirm="Zablokovat? Nebudeš vidět jeho komentáře a odpovědi a on nebude moct reagovat na tvé příspěvky." @endif class="{{ $blocked ? 'btn-outline' : 'btn-danger' }} btn-sm">
        {{ $blocked ? 'Odblokovat' : 'Zablokovat uživatele' }}
    </button>
</div>
