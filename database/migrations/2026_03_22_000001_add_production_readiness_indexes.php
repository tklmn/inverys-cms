<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pages: index on deleted_at for trash queries
        Schema::table('pages', function (Blueprint $table) {
            $table->index('deleted_at', 'pages_deleted_at_index');
        });

        // Media: index on deleted_at for trash queries
        Schema::table('media', function (Blueprint $table) {
            $table->index('deleted_at', 'media_deleted_at_index');
        });

        // Notification reads: index for user lookups
        if (Schema::hasTable('cms_notification_reads')) {
            Schema::table('cms_notification_reads', function (Blueprint $table) {
                $table->index('user_id', 'cms_notification_reads_user_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_deleted_at_index');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_deleted_at_index');
        });

        if (Schema::hasTable('cms_notification_reads')) {
            Schema::table('cms_notification_reads', function (Blueprint $table) {
                $table->dropIndex('cms_notification_reads_user_id_index');
            });
        }
    }
};
