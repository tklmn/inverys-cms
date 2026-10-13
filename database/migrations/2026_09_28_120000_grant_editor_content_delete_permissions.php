<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editors manage content completely — they could always delete pages, but
 * not posts, blog categories, forms, submissions and albums
 * until now. The plugins now grant the delete permission themselves
 * (syncPermissions()), but that only runs when a plugin is activated.
 *
 * Existing installations receive the delete permissions here once:
 * - only if the `editor` role exists,
 * - only permissions that exist (the plugin was installed),
 * - only if the editor still has the matching edit permission — if someone
 *   deliberately took that area away from them, the delete permission does
 *   not bring it back,
 * - purely additive: nothing is revoked (not even the editor's former
 *   `blog-settings`).
 */
return new class extends Migration
{
    /** Delete permission => edit permission the editor must have for it */
    private const GRANTS = [
        'delete-post' => 'edit-post',
        'delete-blog-category' => 'edit-blog-category',
        'delete-form' => 'edit-form',
        'delete-form-submission' => 'edit-form-submission',
        'delete-gallery-album' => 'edit-gallery-album',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $editorId = DB::table('roles')->where('name', 'editor')->value('id');
        if (! $editorId) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_merge(array_keys(self::GRANTS), array_values(self::GRANTS)))
            ->pluck('id', 'name');

        $held = DB::table('role_permission')->where('role_id', $editorId)->pluck('permission_id')->flip();

        $now = now();
        foreach (self::GRANTS as $delete => $edit) {
            if (! isset($ids[$delete], $ids[$edit])) {
                continue;
            }
            if (! $held->has($ids[$edit]) || $held->has($ids[$delete])) {
                continue;
            }

            DB::table('role_permission')->insert([
                'role_id' => $editorId,
                'permission_id' => $ids[$delete],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately empty: after the migration it is no longer possible to tell
        // which permissions came from here and which an admin granted themselves
    }
};
