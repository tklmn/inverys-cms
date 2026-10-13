<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add softDeletes to users table
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Check if roles table exists and add softDeletes
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // Check if permissions table exists and add softDeletes
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (! Schema::hasColumn('permissions', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove softDeletes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove softDeletes from roles table if it exists
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (Schema::hasColumn('roles', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        // Remove softDeletes from permissions table if it exists
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (Schema::hasColumn('permissions', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
