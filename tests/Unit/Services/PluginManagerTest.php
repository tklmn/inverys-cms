<?php

namespace Tests\Unit\Services;

use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginManagerTest extends TestCase
{
    use RefreshDatabase;

    private PluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new PluginManager;
    }

    public function test_can_be_resolved_from_container(): void
    {
        $manager = app(PluginManager::class);

        $this->assertInstanceOf(PluginManager::class, $manager);
    }

    public function test_discover_finds_plugins_in_directory(): void
    {
        $this->manager->discover();

        $all = $this->manager->all();

        $this->assertNotEmpty($all, 'No plugins discovered from plugins/ directory.');
    }

    public function test_discovered_plugins_include_known_plugins(): void
    {
        $this->manager->discover();

        $all = $this->manager->all();

        // These plugins exist in the plugins/ directory
        $this->assertArrayHasKey('activity-log', $all);
        $this->assertArrayHasKey('cookie-consent', $all);
    }

    public function test_each_discovered_plugin_has_required_keys(): void
    {
        $this->manager->discover();

        foreach ($this->manager->all() as $slug => $plugin) {
            $this->assertArrayHasKey('slug', $plugin, "Plugin '{$slug}' is missing 'slug'.");
            $this->assertArrayHasKey('name', $plugin, "Plugin '{$slug}' is missing 'name'.");
        }
    }

    public function test_has_returns_true_for_discovered_plugin(): void
    {
        $this->manager->discover();

        $this->assertTrue($this->manager->has('activity-log'));
    }

    public function test_has_returns_false_for_unknown_plugin(): void
    {
        $this->manager->discover();

        $this->assertFalse($this->manager->has('nonexistent-plugin'));
    }

    public function test_get_returns_plugin_data(): void
    {
        $this->manager->discover();

        $plugin = $this->manager->get('activity-log');

        $this->assertNotNull($plugin);
        $this->assertSame('Activity Log', $plugin['name']);
        $this->assertArrayHasKey('provider', $plugin);
    }

    public function test_get_returns_null_for_unknown_plugin(): void
    {
        $this->manager->discover();

        $this->assertNull($this->manager->get('nonexistent-plugin'));
    }

    public function test_all_returns_empty_before_discover(): void
    {
        $fresh = new PluginManager;

        $this->assertSame([], $fresh->all());
    }

    public function test_discover_populates_path_for_each_plugin(): void
    {
        $this->manager->discover();

        foreach ($this->manager->all() as $slug => $plugin) {
            $this->assertArrayHasKey('path', $plugin, "Plugin '{$slug}' is missing 'path'.");
            $this->assertDirectoryExists($plugin['path']);
        }
    }
}
