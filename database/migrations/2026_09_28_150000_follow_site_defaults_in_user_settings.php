<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profiles follow the site default again.
 *
 * The profile form used to show the global value (language, time zone,
 * date format) and wrote it into the profile on save — even if the
 * user had never changed it. If the admin later changed the
 * default, it no longer reached these users. An empty date format
 * even overrode the site default.
 *
 * Therefore only entries that are empty or exactly match the current
 * default are removed — nothing changes visibly. Differing,
 * i.e. deliberately chosen values stay.
 */
return new class extends Migration
{
    private const KEYS = ['language', 'timezone', 'date_format'];

    public function up(): void
    {
        if (! Schema::hasTable('user_settings') || ! Schema::hasTable('settings')) {
            return;
        }

        foreach (self::KEYS as $key) {
            $site = DB::table('settings')->where('key', $key)->whereNull('deleted_at')->value('value');

            DB::table('user_settings')
                ->where('key', $key)
                ->where(function ($query) use ($site) {
                    $query->whereNull('value')->orWhere('value', '');
                    if ($site !== null && $site !== '') {
                        $query->orWhere('value', $site);
                    }
                })
                ->delete();
        }
    }

    public function down(): void
    {
        // Not reversible: without an own entry the same value applies anyway
    }
};
