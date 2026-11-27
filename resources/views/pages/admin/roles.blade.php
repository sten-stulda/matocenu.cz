<?php

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin')] #[Title('Role a oprávnění')] class extends Component {
    public function with(): array
    {
        $counts = \Spatie\Permission\Models\Role::withCount('users')->pluck('users_count', 'name');

        return ['roles' => Role::cases(), 'permissions' => Permission::LABELS, 'counts' => $counts];
    }
}; ?>

<div class="space-y-4">
    <p class="max-w-3xl text-sm text-muted">Mapování rolí na oprávnění je definované v kódu (<code>App\Domain\Identity\Enums\Role</code>), aby bylo verzované a revidovatelné. Role uživatelům přidělíš v sekci <a class="link" href="{{ route('admin.users') }}">Uživatelé</a>. Superadmin má vždy všechna oprávnění.</p>
    <div class="card overflow-x-auto p-0">
        <table class="table-admin">
            <thead><tr><th>Oprávnění</th>@foreach ($roles as $r)@if ($r->value !== 'user')<th class="text-center">{{ $r->label() }}<br><span class="font-normal">({{ $counts[$r->value] ?? 0 }})</span></th>@endif @endforeach</tr></thead>
            <tbody>
            @foreach ($permissions as $key => $label)
                <tr>
                    <td>{{ $label }} <span class="text-xs text-muted">{{ $key }}</span></td>
                    @foreach ($roles as $r)
                        @if ($r->value !== 'user')
                            <td class="text-center">@if (in_array($key, $r->permissions(), true))<x-icon name="check" :size="18" class="mx-auto text-ok" /><span class="sr-only">ano</span>@else<span class="text-muted">·</span>@endif</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
