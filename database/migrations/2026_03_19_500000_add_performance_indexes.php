<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds performance indexes on columns frequently used in
     * WHERE, ORDER BY, and JOIN clauses.
     *
     * Skipped indexes that already exist:
     * - pages.parent_id  — created automatically by foreignId()->constrained()
     * - pages.slug       — already has a unique constraint
     * - media.user_id    — created automatically by foreignId()->constrained()
     * - settings.key     — already has a unique constraint
     */
    public function up(): void
    {
        // Pages: composite index for frontend navigation queries
        // (WHERE is_active = 1 AND parent_id = ? ORDER BY order)
        Schema::table('pages', function (Blueprint $table) {
            $table->index(['is_active', 'parent_id', 'order'], 'pages_active_parent_order_index');
            $table->index('level', 'pages_level_index');
        });

        // Media: indexes for filtering and sorting in the media library
        Schema::table('media', function (Blueprint $table) {
            $table->index('mime_type', 'media_mime_type_index');
            $table->index('created_at', 'media_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_active_parent_order_index');
            $table->dropIndex('pages_level_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_mime_type_index');
            $table->dropIndex('media_created_at_index');
        });
    }
};
