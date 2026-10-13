<?php

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use App\Support\BuilderTexts;
use App\Support\InstallerLanguages;
use App\Support\Locales;
use App\Support\PermissionGroup;
use App\Support\ScriptTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizationHelpersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register German like the language pack does, including its language files
        app(Locales::class)->register('de', 'German', 'Deutsch', base_path('plugins/language-de/lang'));
    }

    public function test_dates_follow_the_interface_language(): void
    {
        $date = Carbon::create(2026, 9, 24, 15, 4);

        app()->setLocale('en');
        $this->assertSame('09/24/2026', format_date($date));
        $this->assertSame('September 24, 2026', format_date_long($date));
        $this->assertSame('09/24/2026 3:04 PM', format_datetime($date));

        app()->setLocale('de');
        $this->assertSame('24.09.2026', format_date($date));
        $this->assertSame('24. September 2026', format_date_long($date));
        $this->assertSame('24.09.2026 15:04', format_datetime($date));
    }

    public function test_a_date_format_from_the_profile_wins(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'date_format', 'value' => 'j. F Y']);
        $this->actingAs($user);

        app()->setLocale('de');
        $this->assertSame('24. September 2026', format_date(Carbon::create(2026, 9, 24)));
    }

    public function test_the_time_follows_a_custom_date_format(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id, 'key' => 'date_format', 'value' => 'd.m.Y']);
        $this->actingAs($user);

        // English interface, German date: then 24-hour time too
        app()->setLocale('en');
        $this->assertSame('24.09.2026 15:04', format_datetime(Carbon::create(2026, 9, 24, 15, 4)));

        $us = User::factory()->create();
        UserSetting::create(['user_id' => $us->id, 'key' => 'date_format', 'value' => 'm/d/Y']);
        $this->actingAs($us);
        $this->assertSame('09/24/2026 3:04 PM', format_datetime(Carbon::create(2026, 9, 24, 15, 4)));
    }

    public function test_missing_dates_render_empty(): void
    {
        $this->assertSame('', format_date(null));
        $this->assertSame('', format_datetime(''));
    }

    public function test_permission_labels_are_built_from_verb_and_subject(): void
    {
        app()->setLocale('en');
        $this->assertSame('Edit pages', PermissionGroup::permissionLabel('edit-page'));
        $this->assertSame('Manage blog settings', PermissionGroup::permissionLabel('blog-settings'));
        $this->assertSame('Access the API', PermissionGroup::permissionLabel('api-access'));

        app()->setLocale('de');
        $this->assertSame('Seiten bearbeiten', PermissionGroup::permissionLabel('edit-page'));
        $this->assertSame('Blog-Einstellungen verwalten', PermissionGroup::permissionLabel('blog-settings'));

        // Unknown subjects (third-party plugins) stay readable
        app()->setLocale('en');
        $this->assertSame('Edit shop orders', PermissionGroup::permissionLabel('edit-shop-orders'));
    }

    public function test_installer_finds_language_packs_on_disk(): void
    {
        $languages = InstallerLanguages::all();

        $this->assertArrayHasKey('en', $languages);
        $this->assertArrayHasKey('de', $languages);
        $this->assertSame('Deutsch', $languages['de']['native']);
        $this->assertFalse(InstallerLanguages::has('xx'));

        $this->assertTrue(InstallerLanguages::use('de'));
        $this->assertSame('de', app()->getLocale());
        $this->assertNotSame('install.title', __('install.title'));
    }

    public function test_editor_language_file_is_found_by_code_or_region(): void
    {
        $public = storage_path('framework/testing/public');
        File::ensureDirectoryExists($public.'/vendor/tinymce/langs');
        File::put($public.'/vendor/tinymce/langs/de.js', '');
        File::put($public.'/vendor/tinymce/langs/fr-FR.js', '');
        app()->usePublicPath($public);

        try {
            $this->assertNull(ScriptTexts::tinymce('en'));
            $this->assertSame('de', ScriptTexts::tinymce('de')['code']);
            $this->assertSame('fr-FR', ScriptTexts::tinymce('fr')['code']);
            $this->assertNull(ScriptTexts::tinymce('xx'));
        } finally {
            File::deleteDirectory($public);
        }
    }

    public function test_builder_reset_label_changes_in_translation_mode(): void
    {
        app()->setLocale('en');

        $this->assertSame(__('translation.reset_to_default'), BuilderTexts::all()['reset_default']);
        $this->assertSame(__('translation.locales.take_original'), BuilderTexts::all(true)['reset_default']);
    }

    public function test_mails_use_the_language_of_the_recipient(): void
    {
        Setting::updateOrCreate(['key' => 'language'], ['value' => 'en']);
        app(Locales::class)->flush();

        $german = User::factory()->create();
        UserSetting::create(['user_id' => $german->id, 'key' => 'language', 'value' => 'de']);
        $unknown = User::factory()->create();
        UserSetting::create(['user_id' => $unknown->id, 'key' => 'language', 'value' => 'it']);

        $this->assertSame('de', $german->preferredLocale());
        $this->assertSame('en', $unknown->preferredLocale());
    }
}
