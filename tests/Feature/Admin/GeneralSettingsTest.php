<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Support\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * Settings → General: every setting has exactly one place.
 * Keys with their own page are listed locked with a link and
 * cannot be saved either by ID or via the list.
 */
class GeneralSettingsTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    private function setting(string $key, string $value, ?string $type = 'string'): Setting
    {
        // Some keys are already created by the migrations (active_theme, revisions.*)
        return Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
    }

    public function test_list_shows_only_registered_general_keys(): void
    {
        $this->setting('site_name', 'Test Site');
        // Settings of a disabled plugin: its prefix is not registered,
        // yet they must not show up here (including the SMTP user)
        $smtp = $this->setting('mailproviders.smtp.username', 'mailer@example.org');
        $this->setting('blog.url_prefix', 'blog');
        $this->setting('announcement_bar.message', 'Hello', 'plugin_setting');

        $response = $this->actingAs($this->createAdmin())->get(route('backend.settings.index'));

        $response->assertOk()
            ->assertSee('Test Site')
            ->assertDontSee('mailproviders.smtp.username')
            ->assertDontSee('mailer@example.org')
            ->assertDontSee('blog.url_prefix');

        $this->actingAs($this->createAdmin())->get(route('backend.settings.edit', $smtp))->assertNotFound();
        $this->actingAs($this->createAdmin())
            ->put(route('backend.settings.update', $smtp), ['value' => 'evil'])
            ->assertNotFound();
        $this->assertSame('mailer@example.org', $smtp->fresh()->value);
    }

    public function test_active_theme_is_shown_locked_with_link_to_themes(): void
    {
        $this->setting('active_theme', 'default');

        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.index'))
            ->assertOk()
            ->assertSee(__('translation.settings.managed_by', ['page' => __('translation.navigation.themes')]))
            ->assertSee(route('backend.themes.index'), false);
    }

    public function test_active_theme_cannot_be_edited_by_id(): void
    {
        $admin = $this->createAdmin();
        $setting = $this->setting('active_theme', 'default');

        $this->actingAs($admin)
            ->get(route('backend.settings.edit', $setting))
            ->assertRedirect(route('backend.themes.index'));

        // No free text without validation — not even a theme that does not exist
        $this->actingAs($admin)
            ->put(route('backend.settings.update', $setting), ['value' => 'does-not-exist'])
            ->assertForbidden();

        $this->assertSame('default', $setting->fresh()->value);
    }

    public function test_values_are_validated_per_key(): void
    {
        $admin = $this->createAdmin();
        $cases = [
            ['timezone', 'Europe/Berlin', 'Mars/Olympus'],
            ['language', 'en', 'xx'],
            ['theme', 'light', 'blue'],
            ['revisions.max_per_model', '50', 'many'],
            ['favicon', 'images/favicon.svg', '../../.env'],
        ];

        foreach ($cases as [$key, $valid, $invalid]) {
            $setting = $this->setting($key, $valid);

            $this->actingAs($admin)
                ->put(route('backend.settings.update', $setting), ['value' => $invalid])
                ->assertSessionHasErrors('value');

            $this->assertSame($valid, $setting->fresh()->value, $key);
        }
    }

    public function test_valid_values_are_saved_and_date_format_may_be_empty(): void
    {
        $admin = $this->createAdmin();
        $timezone = $this->setting('timezone', 'Europe/Berlin');
        $format = $this->setting('date_format', 'd.m.Y');

        $this->actingAs($admin)
            ->put(route('backend.settings.update', $timezone), ['value' => 'America/New_York'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('America/New_York', $timezone->fresh()->value);

        // Empty = format of the interface language
        $this->actingAs($admin)
            ->put(route('backend.settings.update', $format), ['value' => ''])
            ->assertSessionHasNoErrors();
        $this->assertSame('', $format->fresh()->value);
    }

    public function test_edit_page_explains_the_user_override(): void
    {
        $setting = $this->setting('timezone', 'Europe/Berlin');

        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.edit', $setting))
            ->assertOk()
            ->assertSee(__('translation.settings.user_override_title'))
            ->assertSee(__('translation.settings.general_help.timezone'));
    }

    public function test_with_multilingual_plugin_the_default_language_is_managed_on_the_languages_page(): void
    {
        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
        $admin = $this->createAdmin();
        $language = Setting::where('key', 'language')->first() ?? $this->setting('language', 'en');

        $this->assertNotNull(GeneralSettings::managedBy('language'));

        $this->actingAs($admin)
            ->get(route('backend.settings.index'))
            ->assertOk()
            ->assertSee(__('translation.settings.managed_by', ['page' => __('multilingual::ml.title')]));

        $this->actingAs($admin)
            ->put(route('backend.settings.update', $language), ['value' => 'de'])
            ->assertForbidden();
        $this->assertSame('en', $language->fresh()->value);

        // The languages page is the one place for it
        $this->actingAs($admin)
            ->put(route('backend.multilingual.settings.update'), ['default_locale' => 'de'])
            ->assertRedirect();
        $this->assertSame('de', $language->fresh()->value);
    }

    public function test_without_multilingual_plugin_the_default_language_is_edited_here(): void
    {
        $this->assertNull(GeneralSettings::managedBy('language'));

        $setting = $this->setting('language', 'en');

        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.edit', $setting))
            ->assertOk()
            ->assertSee(__('translation.settings.general_help.language'));
    }
}
