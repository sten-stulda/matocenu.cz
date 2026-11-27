<?php

use App\Domain\Identity\Actions\ManageUser;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Uživatelé')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Locked]
    public ?int $editingId = null;

    /** @var list<string> */
    public array $editRoles = [];

    public ?int $suspendDays = 7;
    public string $suspendReason = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedRole(): void { $this->resetPage(); }

    public function with(): array
    {
        $q = trim($this->search);
        $like = '%'.addcslashes($q, '%_\\').'%';

        return [
            'users' => User::query()->with('roles:id,name')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', $like)->when(auth()->user()->can('users.manage'), fn ($y) => $y->orWhere('email', 'ilike', $like))))
                ->when($this->role !== '', fn ($w) => $w->role($this->role))
                ->orderByDesc('created_at')->paginate(25),
            'roles' => Role::cases(),
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('users.manage');
        $this->editingId = $id;
        $this->editRoles = User::findOrFail($id)->getRoleNames()->all();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    public function saveRoles(ManageUser $manage): void
    {
        try {
            $manage->syncRoles(auth()->user(), User::findOrFail($this->editingId), $this->editRoles);
            $this->editingId = null;
            session()->flash('status', 'Role uloženy.');
        } catch (AuthorizationException $e) {
            $this->addError('editRoles', $e->getMessage() ?: 'Nemáš oprávnění.');
        }
    }

    public function suspend(int $id, ManageUser $manage): void
    {
        $this->validate(['suspendReason' => 'required|string|min:5|max:500', 'suspendDays' => 'nullable|integer|min:1|max:3650']);
        try {
            $manage->suspend(auth()->user(), User::findOrFail($id), $this->suspendDays, $this->suspendReason);
            $this->reset('suspendReason');
            session()->flash('status', 'Účet pozastaven.');
        } catch (AuthorizationException $e) {
            session()->flash('error', $e->getMessage() ?: 'Nelze.');
        }
    }

    public function reactivate(int $id, ManageUser $manage): void
    {
        $manage->reactivate(auth()->user(), User::findOrFail($id));
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" class="input max-w-xs" placeholder="Hledat přezdívku{{ auth()->user()->can('users.manage') ? ' nebo e-mail' : '' }}…" aria-label="Hledat">
        <select wire:model.live="role" class="input max-w-[220px]" aria-label="Role">
            <option value="">Všechny role</option>
            @foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
        </select>
    </div>

    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Přezdívka</th>@can('users.manage')<th>E-mail</th>@endcan<th>Role</th><th>Stav</th><th>Registrace</th><th>Naposledy</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr wire:key="u{{ $u->id }}">
                    <td class="font-semibold">{{ $u->name }}</td>
                    @can('users.manage')<td class="text-muted">{{ $u->email }}@if (! $u->email_verified_at) <span class="badge-terra">neověřen</span>@endif</td>@endcan
                    <td>@foreach ($u->roles as $r)@if ($r->name !== 'user')<span class="badge-mustard mr-1">{{ \App\Domain\Identity\Enums\Role::from($r->name)->label() }}</span>@endif @endforeach</td>
                    <td><span class="{{ $u->status->value === 'active' ? 'badge-forest' : 'badge-terra' }}">{{ $u->status->label() }}</span>@if ($u->suspended_until)<span class="text-xs text-muted"> do {{ local_time($u->suspended_until)->format('j. n.') }}</span>@endif</td>
                    <td>{{ local_time($u->created_at)->format('j. n. Y') }}</td>
                    <td>{{ $u->last_seen_at ? local_time($u->last_seen_at)->diffForHumans() : '–' }}</td>
                    <td class="whitespace-nowrap text-right">
                        @if (! $u->isDeleted())
                            @can('users.manage')<button wire:click="edit({{ $u->id }})" class="btn-ghost btn-sm">Role</button>@endcan
                            @can('users.suspend')
                                @if ($u->status->value === 'active')
                                    <span x-data="{ o: false }" class="relative">
                                        <button @click="o = !o" class="btn-danger btn-sm">Pozastavit</button>
                                        <div x-cloak x-show="o" @click.outside="o = false" class="absolute right-0 z-10 mt-2 w-72 space-y-2 rounded-md border border-line bg-surface p-3 text-left shadow-[var(--shadow-pop)]">
                                            <label class="label">Na kolik dní (prázdné = natrvalo)</label>
                                            <input type="number" wire:model="suspendDays" class="input" min="1">
                                            <label class="label">Důvod</label>
                                            <input wire:model="suspendReason" class="input">
                                            @error('suspendReason')<p class="error">{{ $message }}</p>@enderror
                                            <button wire:click="suspend({{ $u->id }})" class="btn-danger btn-sm w-full">Potvrdit</button>
                                        </div>
                                    </span>
                                @else
                                    <button wire:click="reactivate({{ $u->id }})" class="btn-outline btn-sm">Obnovit</button>
                                @endif
                            @endcan
                        @endif
                    </td>
                </tr>
                @if ($editingId === $u->id)
                    <tr><td colspan="7" class="bg-surface-2">
                        <div class="flex flex-wrap items-center gap-3">
                            @foreach ($roles as $r)
                                @if ($r->value !== 'user')
                                    <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model="editRoles" value="{{ $r->value }}" class="h-4 w-4 accent-forest">{{ $r->label() }}</label>
                                @endif
                            @endforeach
                            <button wire:click="saveRoles" class="btn-forest btn-sm">Uložit role</button>
                            <button wire:click="cancelEdit" class="btn-ghost btn-sm">Zrušit</button>
                        </div>
                        @error('editRoles')<p class="error">{{ $message }}</p>@enderror
                    </td></tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-muted">Nikdo nenalezen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
