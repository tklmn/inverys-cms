<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Focal point of an image (percent from the left / top, 0–100): where the
 * frontend anchors the image when it is cropped (object-fit: cover →
 * object-position). Null means the center. Shared by all languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            if (! Schema::hasColumn('media', 'focal_x')) {
                $table->decimal('focal_x', 5, 2)->nullable()->after('height');
            }
            if (! Schema::hasColumn('media', 'focal_y')) {
                $table->decimal('focal_y', 5, 2)->nullable()->after('focal_x');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter(
            ['focal_x', 'focal_y'],
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
