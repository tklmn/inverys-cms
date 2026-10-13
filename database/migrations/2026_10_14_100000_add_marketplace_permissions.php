<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core permissions of the marketplace (Extensions → Themes / Plugins →
 * Marketplace), given to the admin role:
 *
 *   install-themes, install-plugins  install and update packages from the
 *                                    marketplace sources — any role can get them
 *   manage-marketplace               choose the sources, i.e. which code can be
 *                                    installed at all — administrators only
 *                                    (CodeAccess::ADMIN_ONLY)
 */
return new class extends Migration
{
    private const PERMISSIONS = ['install-themes', 'install-plugins', 'manage-marketplace'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $adminId = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'admin')->value('id') : null;

        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->value('id');

            if ($id) {
                DB::table('permissions')->where('id', $id)->update(['is_system' => true, 'deleted_at' => null]);
            } else {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($adminId && Schema::hasTable('role_permission')
                && ! DB::table('role_permission')->where(['role_id' => $adminId, 'permission_id' => $id])->exists()) {
                DB::table('role_permission')->insert([
                    'role_id' => $adminId,
                    'permission_id' => $id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PERMISSIONS as $name) {
            $id = DB::table('permissions')->where('name', $name)->value('id');

            if ($id) {
                DB::table('role_permission')->where('permission_id', $id)->delete();
                DB::table('permissions')->where('id', $id)->delete();
            }
        }
    }
};
