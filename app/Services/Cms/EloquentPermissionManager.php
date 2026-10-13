<?php

namespace App\Services\Cms;

use App\Contracts\PermissionManager;
use App\Models\Permission;
use App\Models\Role;

class EloquentPermissionManager implements PermissionManager
{
    public function createPermission(string $name): mixed
    {
        // Created from code = checked by code, so not changeable in the UI
        return Permission::updateOrCreate(['name' => $name], ['is_system' => true]);
    }

    public function deletePermission(string $name): bool
    {
        return Permission::where('name', $name)->delete() > 0;
    }

    public function assignToRole(string $roleName, array $permissionNames): void
    {
        // Exact name — see Role::named() (MySQL compares ignoring accents)
        $role = Role::named($roleName);
        if (! $role) {
            return;
        }

        $role->permissions()->syncWithoutDetaching($this->permissionIds($permissionNames));
    }

    public function revokeFromRole(string $roleName, array $permissionNames): void
    {
        $role = Role::named($roleName);
        if (! $role) {
            return;
        }

        $role->permissions()->detach($this->permissionIds($permissionNames));
    }

    /**
     * IDs of the permissions with exactly these names — for the same reason as
     * with roles, not left to the database comparison alone.
     *
     * @param  array<string>  $names
     * @return list<int>
     */
    private function permissionIds(array $names): array
    {
        return Permission::whereIn('name', $names)->get()
            ->filter(fn (Permission $permission) => in_array($permission->name, $names, true))
            ->pluck('id')
            ->values()
            ->all();
    }

    public function userHasPermission(string $permissionName): bool
    {
        $user = auth()->user();

        return $user && $user->hasPermission($permissionName);
    }
}
