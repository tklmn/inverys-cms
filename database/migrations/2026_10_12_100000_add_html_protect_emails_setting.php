<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → General → "Protect email addresses"
 * (App\Http\Middleware\ProtectEmailAddresses). Default: on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::firstOrCreate(
            ['key' => 'html.protect_emails'],
            ['value' => '1', 'type' => 'string', 'description' => 'Hide email addresses on the website from spam bots']
        );
    }

    public function down(): void
    {
        Setting::where('key', 'html.protect_emails')->delete();
    }
};
