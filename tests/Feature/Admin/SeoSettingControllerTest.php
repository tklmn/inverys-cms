<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Support\PermissionGroup;
use App\Support\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

class SeoSettingControllerTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->admin = $this->createAdmin();
    }

    /** @return array<string, string> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'sitemap_enabled' => 'on',
            'sitemap_cache_ttl' => '60',
            'robots_allow_indexing' => 'on',
            'robots_mode' => 'auto',
            'robots_sitemap_line' => 'on',
            'robots_extra' => '',
            'robots_custom' => '',
        ], $overrides);
    }

    public function test_admin_sees_the_seo_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backend.settings.seo'))
            ->assertOk()
            ->assertSee(__('translation.seo.title'))
            ->assertSee(__('translation.seo.robots_allow'))
            ->assertSee(__('translation.seo.sitemap_pages'))
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_page_lists_a_switch_per_toggleable_provider(): void
    {
        Storage::fake('public');
        $this->loadPlugins(['blog' => 'Blog']);

        $this->actingAs($this->admin)
            ->get(route('backend.settings.seo'))
            ->assertOk()
            ->assertSee('name="sitemap_include_blog"', false)
            ->assertSee('name="sitemap_include_blog_categories"', false)
            ->assertSee(__('blog::blog.sitemap_posts'));
    }

    public function test_editor_has_no_access(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)->get(route('backend.settings.seo'))->assertForbidden();
        $this->actingAs($editor)->post(route('backend.settings.seo.save'), $this->validPayload())->assertForbidden();
        $this->assertNull(Setting::where('key', 'seo.sitemap_enabled')->value('value'));
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('backend.settings.seo'))->assertRedirect(route('login'));
    }

    public function test_settings_are_saved(): void
    {
        Storage::fake('public');
        $this->loadPlugins(['blog' => 'Blog']);

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->validPayload([
                'sitemap_enabled' => 'off',
                'sitemap_cache_ttl' => '1440',
                'robots_allow_indexing' => 'off',
                'robots_extra' => "# Comment\r\nUser-agent: BadBot\r\nDisallow: /\r\nCrawl-delay: 10",
                'sitemap_include_blog' => 'off',
                'sitemap_include_blog_categories' => 'on',
                'robots_mode' => 'custom',
                'robots_sitemap_line' => 'off',
                'robots_custom' => "User-agent: *\r\nDisallow: /tmp/\r\nSitemap: https://example.org/other.xml",
            ]))
            ->assertRedirect(route('backend.settings.seo'))
            ->assertSessionHasNoErrors();

        $saved = Setting::where('key', 'like', 'seo.%')->pluck('value', 'key')->all();

        $this->assertSame('custom', $saved['seo.robots_mode']);
        $this->assertSame('off', $saved['seo.robots_sitemap_line']);
        $this->assertSame("User-agent: *\nDisallow: /tmp/\nSitemap: https://example.org/other.xml", $saved['seo.robots_custom']);

        $this->assertSame('off', $saved['seo.sitemap_enabled']);
        $this->assertSame('1440', $saved['seo.sitemap_cache_ttl']);
        $this->assertSame('off', $saved['seo.robots_allow_indexing']);
        $this->assertSame("# Comment\nUser-agent: BadBot\nDisallow: /\nCrawl-delay: 10", $saved['seo.robots_extra']);
        $this->assertSame('off', $saved['seo.sitemap_include_blog']);
        $this->assertSame('on', $saved['seo.sitemap_include_blog_categories']);

        // SEO settings do not belong in the general settings
        $this->actingAs($this->admin)->get(route('backend.settings.index'))->assertDontSee('seo.sitemap_enabled');
    }

    public function test_invalid_robots_rules_are_rejected(): void
    {
        foreach ([
            '<script>alert(1)</script>',
            "User-agent: *\n<b>Disallow: /</b>",
            'Sitemap: https://example.org/other.xml',
            'Noindex: /private',
            'just some text',
            '# <img src=x>',
        ] as $rules) {
            $this->actingAs($this->admin)
                ->from(route('backend.settings.seo'))
                ->post(route('backend.settings.seo.save'), $this->validPayload(['robots_extra' => $rules]))
                ->assertRedirect(route('backend.settings.seo'))
                ->assertSessionHasErrors('robots_extra');
        }

        $this->assertNull(Setting::where('key', 'seo.robots_extra')->value('value'));
    }

    public function test_custom_robots_content_is_validated(): void
    {
        foreach ([
            '<html><body>robots</body></html>',
            "User-agent: *\nNoindex: /",
            'Sitemap: /relative.xml',
            'Sitemap: javascript:alert(1)',
        ] as $content) {
            $this->actingAs($this->admin)
                ->post(route('backend.settings.seo.save'), $this->validPayload(['robots_mode' => 'custom', 'robots_custom' => $content]))
                ->assertSessionHasErrors('robots_custom');
        }

        // Own sitemap lines are allowed in custom content, not in the extra rules
        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->validPayload([
                'robots_mode' => 'custom',
                'robots_custom' => "User-agent: *\nAllow: /\nSitemap: https://example.org/sitemap.xml",
            ]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->validPayload([
                'robots_custom' => str_repeat("Disallow: /x\n", 1000),
                'robots_mode' => 'sometimes',
                'robots_sitemap_line' => 'maybe',
            ]))
            ->assertSessionHasErrors(['robots_custom', 'robots_mode', 'robots_sitemap_line']);
    }

    public function test_page_offers_sitemap_submission_help(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backend.settings.seo'))
            ->assertOk()
            ->assertSee('id="sitemap_url_copy"', false)
            ->assertSee('data-copy="#sitemap_url_copy"', false)
            ->assertSee(SeoSettings::GOOGLE_SEARCH_CONSOLE_URL, false)
            ->assertSee(SeoSettings::BING_WEBMASTER_URL, false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('name="robots_mode"', false)
            ->assertSee('data-requires="robots_mode=custom"', false);
    }

    public function test_page_shows_the_off_mode_and_the_blocked_warning(): void
    {
        Setting::create(['key' => 'seo.robots_mode', 'value' => 'off']);

        $this->actingAs($this->admin)
            ->get(route('backend.settings.seo'))
            ->assertSee(__('translation.seo.robots_off_preview', ['url' => route('robots')]))
            ->assertDontSee(__('translation.seo.indexing_blocked'));

        Setting::create(['key' => 'seo.robots_allow_indexing', 'value' => 'off']);

        $this->actingAs($this->admin)
            ->get(route('backend.settings.seo'))
            ->assertSee(__('translation.seo.indexing_blocked'))
            ->assertSee(__('translation.seo.robots_blocked_note'));
    }

    public function test_backend_header_warns_admins_while_search_engines_are_blocked(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertDontSee('data-indexing-blocked', false);

        Setting::create(['key' => 'seo.robots_allow_indexing', 'value' => 'off']);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('data-indexing-blocked', false)
            ->assertSee(__('translation.seo.indexing_blocked_badge'));

        // Editors cannot change it and do not see the notice
        $this->actingAs($this->createEditor())->get(route('dashboard'))->assertOk()->assertDontSee('data-indexing-blocked', false);
    }

    public function test_too_long_rules_and_unknown_cache_durations_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->validPayload([
                'robots_extra' => str_repeat("Disallow: /x\n", 500),
                'sitemap_cache_ttl' => '7',
                'sitemap_enabled' => 'maybe',
            ]))
            ->assertSessionHasErrors(['robots_extra', 'sitemap_cache_ttl', 'sitemap_enabled']);
    }

    public function test_saving_clears_the_sitemap_cache(): void
    {
        Page::factory()->create(['slug' => 'first']);
        $this->get('/sitemap.xml')->assertOk();

        Page::factory()->create(['slug' => 'second']);
        $this->assertStringNotContainsString(route('page.show', 'second'), $this->get('/sitemap.xml')->getContent());

        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString(route('page.show', 'second'), $this->get('/sitemap.xml')->getContent());
    }

    public function test_permission_is_a_locked_core_settings_permission(): void
    {
        $this->assertContains('manage-seo-settings', Permission::CORE);
        $this->assertSame('settings', PermissionGroup::of('manage-seo-settings'));
    }

    public function test_migration_adds_the_permission_for_existing_admins(): void
    {
        $permission = Permission::where('name', 'manage-seo-settings')->first();
        $permission->roles()->detach();
        $permission->forceDelete();

        (require database_path('migrations/2026_09_28_140000_add_manage_seo_settings_permission.php'))->up();

        $permission = Permission::where('name', 'manage-seo-settings')->first();
        $this->assertNotNull($permission);
        $this->assertTrue($permission->is_system);
        $this->assertSame(['admin'], $permission->roles()->pluck('name')->all());
    }

    public function test_navigation_links_the_seo_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('backend.settings.security'))
            ->assertOk()
            ->assertSee(route('backend.settings.seo'), false);
    }
}
