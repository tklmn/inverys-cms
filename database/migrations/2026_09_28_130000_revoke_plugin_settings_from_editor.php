<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editors manage content, but not settings — that is how the plugins grant
 * it since 3.1 (syncPermissions()). Only the blog plugin had so far also given
 * editors `blog-settings`; it is revoked here once so that old and new
 * installations follow the same rule. Admins can grant it again under
 * Roles.
 */
return new class extends Migration
{
    private const REVOKE = ['blog-settings'];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $editorId = DB::table('roles')->where('name', 'editor')->value('id');
        if (! $editorId) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('name', self::REVOKE)->pluck('id');

        DB::table('role_permission')->where('role_id', $editorId)->whereIn('permission_id', $ids)->delete();
    }

    public function down(): void
    {
        // Deliberately empty: whether the editor had the permission before is no longer known
    }
};
