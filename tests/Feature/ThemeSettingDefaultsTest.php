<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * theme(): without a saved value the default from theme.json applies — a freshly
 * activated theme would otherwise show e.g. no logo until saved in the customizer.
 */
class ThemeSettingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsaved_settings_fall_back_to_the_theme_json_default(): void
    {
        Setting::updateOrCreate(['key' => 'active_theme'], ['value' => 'default']);
        EloquentSettingRepository::clearCache();

        $this->assertSame('images/logo.svg', theme('logo_url'));
        // The theme's default takes precedence over the caller's
        $this->assertSame('#4f46e5', theme('primary_color', '#000000'));
        // Unknown key: the caller's default
        $this->assertSame('fallback', theme('does_not_exist', 'fallback'));
    }

    public function test_saved_values_win(): void
    {
        Setting::updateOrCreate(['key' => 'active_theme'], ['value' => 'default']);
        Setting::updateOrCreate(['key' => 'theme.default.primary_color'], ['value' => '#123456']);
        Setting::updateOrCreate(['key' => 'theme.default.logo_url'], ['value' => '']);
        EloquentSettingRepository::clearCache();

        $this->assertSame('#123456', theme('primary_color'));
        // Deliberately cleared stays empty
        $this->assertSame('', theme('logo_url'));
    }
}
