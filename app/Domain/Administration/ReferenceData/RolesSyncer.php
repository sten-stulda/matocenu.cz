<?php

namespace App\Domain\Administration\ReferenceData;

use App\Domain\Identity\Enums\Permission;
use App\Domain\Identity\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolesSyncer implements ReferenceDataSyncer
{
    public function sync(): string
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Permission::all() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }
        foreach (Role::cases() as $role) {
            $model = RoleModel::findOrCreate($role->value, 'web');
            // Role → permission mapping is defined in code (Role::permissions()).
            $model->syncPermissions($role->permissions());
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return count(Role::cases()).' rolí, '.count(Permission::all()).' oprávnění';
    }
}
