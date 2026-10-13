<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decorative images (ornaments, backgrounds) carry no information: they are
 * output with alt="" so screen readers skip them. Everything else needs an
 * alt text — the flag lets the media library tell "deliberately empty" apart
 * from "forgotten".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('media', 'is_decorative')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->boolean('is_decorative')->default(false)->after('alt_text');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('media', 'is_decorative')) {
            return;
        }

        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('is_decorative');
        });
    }
};
