<div class="space-y-2 {{ $c->depth > 0 ? 'ml-5 border-l-2 border-line pl-4' : '' }}" wire:key="c{{ $c->id }}">
    <div>
        <p class="text-sm"><b>{{ $c->user->isDeleted() ? 'Smazaný účet' : $c->user->name }}</b> <span class="text-muted">· {{ local_time($c->created_at)->diffForHumans() }}@if ($c->edited_at) · upraveno @endif</span>
            @if (! $c->moderation_status->isPublic())<span class="badge-terra">{{ $c->moderation_status->label() }}</span>@endif</p>
        @if ($editing === $c->id)
            <div class="mt-1 space-y-2">
                <textarea wire:model="editBody" rows="2" class="input" aria-label="Upravit komentář"></textarea>
                @error('editBody')<p class="error">{{ $message }}</p>@enderror
                <button wire:click="saveEdit" class="btn-forest btn-sm">Uložit</button> <button wire:click="$set('editing', null)" class="btn-ghost btn-sm">Zrušit</button>
            </div>
        @else
            <p class="whitespace-pre-line text-[15px]">{{ $c->body }}</p>
        @endif
        <div class="mt-1 flex flex-wrap items-center gap-1 text-[12px]">
            @auth
                @if ($c->depth < (int) setting('comments.max_depth'))<button wire:click="$set('replyTo', {{ $c->id }})" class="btn-ghost btn-sm min-h-8">Odpovědět</button>@endif
                @can('update', $c)<button wire:click="startEdit({{ $c->id }})" class="btn-ghost btn-sm min-h-8">Upravit</button>@endcan
                @can('delete', $c)<button wire:click="remove({{ $c->id }})" wire:confirm="Smazat komentář?" class="btn-ghost btn-sm min-h-8">Smazat</button>@endcan
                @if (auth()->id() !== $c->user_id)<livewire:report-button type="comment" :id="$c->id" :key="'rp-c-'.$c->id" />@endif
            @endauth
        </div>
    </div>
    @foreach ($children->get($c->id, collect()) as $child)
        @include('livewire.partials.comment', ['c' => $child, 'children' => $children])
    @endforeach
</div>
