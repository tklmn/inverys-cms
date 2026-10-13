<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * The sidebar marks the page you are on: a plugin's settings page marks
 * "Plugins" (where it is reached from), not the plugin's content entry.
 */
class NavigationActiveStateTest extends TestCase
{
    use LoadsPlugins;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
        $this->loadPlugins(['blog' => 'Blog', 'captcha' => 'Captcha', 'mail-providers' => 'Mail Providers']);
        $this->actingAs($this->createAdmin());
    }

    /** Labels of the sidebar entries marked as the current page */
    private function current(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $nav = explode('</nav>', $html)[0];
        preg_match_all('/aria-current="page"[^>]*>(.*?)<\/a>/s', $nav, $matches);

        return array_map(fn ($m) => trim(preg_replace('/\s+/', ' ', strip_tags($m))), $matches[1]);
    }

    public function test_plugin_settings_mark_plugins_not_the_content_entry(): void
    {
        $this->assertSame([__('translation.navigation.plugins')], $this->current(route('backend.blog.settings')));
        $this->assertSame([__('translation.navigation.plugins')], $this->current(route('backend.captcha.settings')));
    }

    public function test_the_content_pages_keep_their_entry(): void
    {
        $this->assertSame([__('blog::blog.posts')], $this->current(route('backend.blog.index')));
    }

    public function test_a_settings_page_with_its_own_entry_keeps_it(): void
    {
        $this->assertSame([__('mail-providers::mail-providers.mail_providers')], $this->current(route('backend.mail-providers.settings')));
    }
}
