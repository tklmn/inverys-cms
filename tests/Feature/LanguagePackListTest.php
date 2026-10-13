<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Languages → "Manage language packs" leads to the plugin list, filtered
 * to language packs; the languages page names the installed packs.
 */
class LanguagePackListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_plugin_list_can_show_only_language_packs(): void
    {
        $admin = $this->createAdmin();

        $all = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))->assertOk()->getContent();
        $packs = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index', ['type' => 'language']))->assertOk()->getContent();

        $this->assertStringContainsString(__('Language pack German: German user interface and German as a content language for multilingual sites.'), $packs);
        $this->assertStringNotContainsString(__('Mail Providers'), strip_tags($packs));
        $this->assertGreaterThan(substr_count($packs, 'ui-list-row'), substr_count($all, 'ui-list-row'));
        $this->assertStringContainsString('aria-current="page"', $packs);
    }

    public function test_unknown_types_show_every_plugin(): void
    {
        $admin = $this->createAdmin();

        $all = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index'))->getContent();
        $other = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('backend.plugins.index', ['type' => 'whatever']))->getContent();

        $this->assertSame(substr_count($all, 'ui-list-row'), substr_count($other, 'ui-list-row'));
    }
}
