<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026_09_28_150000: profiles that only held a copy of the site default
 * follow the site again. Deliberately different values are kept.
 */
class FollowSiteDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_09_28_150000_follow_site_defaults_in_user_settings.php');
        $migration->up();
    }

    public function test_removes_copies_of_the_site_default_and_empty_values_only(): void
    {
        Setting::create(['key' => 'language', 'value' => 'en']);
        Setting::create(['key' => 'timezone', 'value' => 'Europe/Berlin']);
        Setting::create(['key' => 'date_format', 'value' => 'd.m.Y']);

        $copy = User::factory()->create();
        $own = User::factory()->create();

        foreach (['language' => 'en', 'timezone' => 'Europe/Berlin', 'date_format' => ''] as $key => $value) {
            UserSetting::create(['user_id' => $copy->id, 'key' => $key, 'value' => $value]);
        }
        foreach (['language' => 'de', 'timezone' => 'Asia/Tokyo', 'date_format' => 'Y-m-d', 'theme' => 'light'] as $key => $value) {
            UserSetting::create(['user_id' => $own->id, 'key' => $key, 'value' => $value]);
        }
        UserSetting::create(['user_id' => $copy->id, 'key' => 'theme', 'value' => 'light']);

        $this->runMigration();

        $this->assertSame(['theme'], UserSetting::where('user_id', $copy->id)->pluck('key')->all());
        $this->assertSame(
            ['date_format', 'language', 'theme', 'timezone'],
            UserSetting::where('user_id', $own->id)->orderBy('key')->pluck('key')->all()
        );
    }
}
