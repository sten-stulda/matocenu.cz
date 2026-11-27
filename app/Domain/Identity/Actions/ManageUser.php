<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Administration\Services\AuditLogger;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Admin actions on user accounts – every change is audited. */
class ManageUser
{
    public function __construct(private AuditLogger $audit) {}

    /** @param list<string> $roles */
    public function syncRoles(User $actor, User $target, array $roles): void
    {
        if (! $actor->can('roles.manage') && ! $actor->can('users.manage')) {
            throw new AuthorizationException('Nemáš oprávnění měnit role.');
        }
        $valid = array_map(fn (Role $r) => $r->value, Role::cases());
        $roles = array_values(array_intersect($roles, $valid));
        $isSuper = $actor->hasRole(Role::Superadmin->value);
        // Only superadmins can grant or revoke superadmin/admin.
        foreach ([Role::Superadmin->value, Role::Admin->value] as $privileged) {
            if (! $isSuper && (in_array($privileged, $roles, true) !== $target->hasRole($privileged))) {
                throw new AuthorizationException('Tuto roli může přidělit jen superadmin.');
            }
        }
        if ($actor->is($target) && $target->hasRole(Role::Superadmin->value) && ! in_array(Role::Superadmin->value, $roles, true)) {
            throw new AuthorizationException('Sám sobě superadmina neodebereš.');
        }
        if (! in_array(Role::User->value, $roles, true)) {
            $roles[] = Role::User->value;
        }
        $before = $target->getRoleNames()->all();
        $target->syncRoles($roles);
        $this->audit->log('user.roles_changed', $target, ['before' => $before, 'after' => $roles]);
    }

    public function suspend(User $actor, User $target, ?int $days, string $reason): void
    {
        if (! $actor->can('users.suspend')) {
            throw new AuthorizationException;
        }
        if ($actor->is($target) || $target->hasRole(Role::Superadmin->value)) {
            throw new AuthorizationException('Tento účet nelze pozastavit.');
        }
        DB::transaction(function () use ($target, $days, $reason) {
            $target->forceFill(['status' => UserStatus::Suspended, 'suspended_until' => $days ? now()->addDays($days) : null])->save();
            DB::table('sessions')->where('user_id', $target->id)->delete();
            $target->tokens()->delete();
            $this->audit->log('user.suspended', $target, ['days' => $days, 'reason' => $reason]);
        });
    }

    public function reactivate(User $actor, User $target): void
    {
        if (! $actor->can('users.suspend')) {
            throw new AuthorizationException;
        }
        $target->forceFill(['status' => UserStatus::Active, 'suspended_until' => null])->save();
        $this->audit->log('user.reactivated', $target);
    }
}
