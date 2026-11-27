<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Upozornění')] class extends Component {
    use WithPagination;

    public function markAll(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function open(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $url = $n->data['url'] ?? route('home');
        // Only same-site redirects.
        return $this->redirect(str_starts_with($url, url('/')) ? $url : route('home'));
    }

    public function with(): array
    {
        return ['items' => auth()->user()->notifications()->latest()->paginate(20)];
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    @include('pages.account.nav', ['active' => 'notifications'])
    <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold">Upozornění</h1>
        @if (auth()->user()->unreadNotifications()->exists())<button wire:click="markAll" class="btn-outline btn-sm">Označit vše jako přečtené</button>@endif
    </div>
    <div class="card p-0">
        <ul class="divide-y divide-line">
            @forelse ($items as $n)
                <li>
                    <button wire:click="open('{{ $n->id }}')" class="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-surface-2">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : 'bg-terra' }}" aria-hidden="true"></span>
                        <span class="min-w-0"><b class="block">{{ $n->data['title'] ?? 'Upozornění' }}</b><span class="block text-sm text-muted">{{ $n->data['body'] ?? '' }}</span>
                        <span class="text-[12px] text-muted">{{ local_time($n->created_at)->diffForHumans() }}</span></span>
                    </button>
                </li>
            @empty
                <li class="p-5"><x-empty title="Zatím ti nikdo nic nevzkázal." /></li>
            @endforelse
        </ul>
    </div>
    {{ $items->links() }}
    <p class="text-sm text-muted">Které upozornění chceš dostávat, nastavíš v <a href="{{ route('account.settings') }}" class="link">Nastavení</a>.</p>
</div>
