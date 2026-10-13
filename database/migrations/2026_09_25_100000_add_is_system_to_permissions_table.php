<?php

use App\Contracts\PermissionManager;
use App\Models\Permission;
use App\Services\PluginManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks permissions that the code checks (core and plugins).
 * They can be neither renamed nor deleted in the UI —
 * otherwise, e.g. with `edit-page`, every role would silently lose access.
 *
 * Existing installations need no manual step for this: the permissions of the
 * active plugins are collected here via their syncPermissions() —
 * with a recorder instead of the real PermissionManager, so that
 * no role assignment is changed in the process.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('name');
        });

        DB::table('permissions')->whereIn('name', array_merge(Permission::CORE, $this->pluginPermissions()))
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }

    /** @return list<string> */
    private function pluginPermissions(): array
    {
        if (! app()->bound(PluginManager::class)) {
            return [];
        }

        $recorder = new class implements PermissionManager
        {
            /** @var list<string> */
            public array $names = [];

            public function createPermission(string $name): mixed
            {
                $this->names[] = $name;

                return null;
            }

            public function deletePermission(string $name): bool
            {
                return false;
            }

            public function assignToRole(string $roleName, array $permissionNames): void {}

            public function revokeFromRole(string $roleName, array $permissionNames): void {}

            public function userHasPermission(string $permissionName): bool
            {
                return false;
            }
        };

        $previous = app(PermissionManager::class);
        app()->instance(PermissionManager::class, $recorder);

        try {
            foreach (app(PluginManager::class)->getLoadedProviders() as $provider) {
                try {
                    $provider->syncPermissions();
                } catch (Throwable) {
                    // A faulty plugin must not hold up the migration
                }
            }
        } finally {
            app()->instance(PermissionManager::class, $previous);
        }

        return array_values(array_unique($recorder->names));
    }
};
