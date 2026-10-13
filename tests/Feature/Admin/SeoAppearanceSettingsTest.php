<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Seo\DocumentTitle;
use App\Support\TranslatableSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settings → SEO, "Search results and social networks", and the SEO
 * fields of the page editor.
 */
class SeoAppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->admin = $this->createAdmin();
    }

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
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

    private function value(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    public function test_settings_page_shows_the_new_fields(): void
    {
        $this->actingAs($this->admin)->get(route('backend.settings.seo'))
            ->assertOk()
            ->assertSee(__('translation.seo.default_description'))
            ->assertSee(__('translation.seo.default_og_image'))
            ->assertSee(__('translation.seo.google_verification'))
            ->assertSee(route('backend.settings.seo.redirects'), false);
    }

    public function test_social_and_title_settings_are_saved(): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->payload([
                'default_description' => "We build\nwebsites.",
                'title_order' => 'site_title',
                'title_separator' => '|',
                'default_og_image' => 'storage/media/share.jpg',
                'organization_logo' => 'https://cdn.example.org/logo.png',
                'social_profiles' => "https://github.com/example\n\nhttps://github.com/example\nhttps://www.linkedin.com/company/example",
                'google_verification' => '<meta name="google-site-verification" content="AbC-123_x" />',
                'bing_verification' => 'B1NGCODE',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('We build websites.', $this->value('seo.default_description'));
        $this->assertSame(DocumentTitle::ORDER_SITE_FIRST, $this->value('seo.title_order'));
        $this->assertSame('|', $this->value('seo.title_separator'));
        $this->assertSame('storage/media/share.jpg', $this->value('seo.default_og_image'));
        $this->assertSame("https://github.com/example\nhttps://www.linkedin.com/company/example", $this->value('seo.social_profiles'));
        // The code from the pasted meta tag
        $this->assertSame('AbC-123_x', $this->value('seo.google_verification'));
        $this->assertSame('B1NGCODE', $this->value('seo.bing_verification'));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('backend.settings.seo.save'), $this->payload([
                'title_order' => 'sideways',
                'title_separator' => '<',
                'social_profiles' => "https://github.com/example\njavascript:alert(1)",
                'google_verification' => '"><script>',
                'default_og_image' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors(['title_order', 'title_separator', 'social_profiles', 'google_verification', 'default_og_image']);
    }

    public function test_default_description_is_translatable(): void
    {
        $this->assertTrue(app(TranslatableSettings::class)->has('seo.default_description'));
    }

    public function test_page_editor_saves_and_validates_the_seo_fields(): void
    {
        $page = Page::factory()->create(['slug' => 'about-us', 'title' => 'About us']);

        $this->actingAs($this->admin)
            ->put(route('backend.pages.update', $page), [
                'title' => 'About us',
                'slug' => 'about-us',
                'canonical_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('canonical_url');

        $this->actingAs($this->admin)
            ->put(route('backend.pages.update', $page), [
                'title' => 'About us',
                'slug' => 'about-us',
                'canonical_url' => 'https://example.org/about',
                'og_title' => 'Meet us',
                'og_description' => 'Twelve people.',
                'og_image' => 'storage/media/team.jpg',
            ])
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame('https://example.org/about', $page->canonical_url);
        $this->assertSame('Meet us', $page->og_title);
        $this->assertSame('storage/media/team.jpg', $page->og_image);

        $this->actingAs($this->admin)->get(route('backend.pages.edit', $page))
            ->assertOk()
            ->assertSee('name="canonical_url"', false)
            ->assertSee('name="og_image"', false);
    }
}
