<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Responsive images: the intrinsic size of an image (width/height attributes
 * against layout shift, og:image size) and the scaled copies generated on
 * upload (srcset). Existing images get them with `php artisan media:variants`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (! Schema::hasColumn('media', 'width')) {
                $table->unsignedInteger('width')->nullable()->after('size');
            }
            if (! Schema::hasColumn('media', 'height')) {
                $table->unsignedInteger('height')->nullable()->after('width');
            }
            if (! Schema::hasColumn('media', 'variants')) {
                $table->json('variants')->nullable()->after('height');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['width', 'height', 'variants'],
            fn (string $column) => Schema::hasColumn('media', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('media', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
