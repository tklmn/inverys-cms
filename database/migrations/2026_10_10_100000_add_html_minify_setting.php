<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → General → "Minify HTML" (App\Http\Middleware\MinifyHtml).
 * Default: on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::firstOrCreate(
            ['key' => 'html.minify'],
            ['value' => '1', 'type' => 'string', 'description' => 'Deliver HTML pages without indentation and comments']
        );
    }

    public function down(): void
    {
        Setting::where('key', 'html.minify')->delete();
    }
};
