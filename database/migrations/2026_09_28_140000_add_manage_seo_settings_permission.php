<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * New core permission `manage-seo-settings` (Settings → SEO: sitemap
 * and robots.txt). Locked like the other core permissions (is_system) and only
 * for administrators — editors do not get it.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage-seo-settings';

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $id = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if ($id) {
            DB::table('permissions')->where('id', $id)->update(['is_system' => true, 'deleted_at' => null]);
        } else {
            $id = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminId = Schema::hasTable('roles') ? DB::table('roles')->where('name', 'admin')->value('id') : null;

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

    public function down(): void
    {
        $id = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
