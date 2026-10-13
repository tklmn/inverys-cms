<?php

namespace App\Contracts;

interface PermissionManager
{
    public function createPermission(string $name): mixed;

    public function deletePermission(string $name): bool;

    public function assignToRole(string $roleName, array $permissionNames): void;

    public function revokeFromRole(string $roleName, array $permissionNames): void;

    public function userHasPermission(string $permissionName): bool;
}
