<?php

namespace Tests\Unit\Models;

use App\Models\Plugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_plugin(): void
    {
        $plugin = Plugin::factory()->create([
            'slug' => 'seo-tools',
            'name' => 'SEO Tools',
            'version' => '2.1.0',
        ]);

        $this->assertDatabaseHas('plugins', [
            'slug' => 'seo-tools',
            'name' => 'SEO Tools',
            'version' => '2.1.0',
        ]);
        $this->assertEquals('seo-tools', $plugin->slug);
    }

    public function test_is_enabled_cast_to_boolean(): void
    {
        $plugin = Plugin::factory()->create(['is_enabled' => 1]);

        $this->assertIsBool($plugin->is_enabled);
        $this->assertTrue($plugin->is_enabled);

        $plugin2 = Plugin::factory()->create(['is_enabled' => 0]);

        $this->assertIsBool($plugin2->is_enabled);
        $this->assertFalse($plugin2->is_enabled);
    }

    public function test_is_system_cast_to_boolean(): void
    {
        $plugin = Plugin::factory()->create(['is_system' => 1]);

        $this->assertIsBool($plugin->is_system);
        $this->assertTrue($plugin->is_system);

        $plugin2 = Plugin::factory()->create(['is_system' => 0]);

        $this->assertIsBool($plugin2->is_system);
        $this->assertFalse($plugin2->is_system);
    }

    public function test_settings_cast_to_array(): void
    {
        $settingsData = ['color' => 'blue', 'limit' => 10];
        $plugin = Plugin::factory()->create(['settings' => $settingsData]);

        $plugin->refresh();

        $this->assertIsArray($plugin->settings);
        $this->assertEquals('blue', $plugin->settings['color']);
        $this->assertEquals(10, $plugin->settings['limit']);
    }

    public function test_installed_at_cast_to_datetime(): void
    {
        $date = Carbon::parse('2026-01-15 10:30:00');
        $plugin = Plugin::factory()->create(['installed_at' => $date]);

        $this->assertInstanceOf(Carbon::class, $plugin->installed_at);
        $this->assertTrue($date->equalTo($plugin->installed_at));
    }

    public function test_soft_delete_works(): void
    {
        $plugin = Plugin::factory()->create();
        $plugin->delete();

        $this->assertSoftDeleted('plugins', ['id' => $plugin->id]);
        $this->assertNull(Plugin::find($plugin->id));
        $this->assertNotNull(Plugin::withTrashed()->find($plugin->id));
    }

    public function test_enabled_factory_state(): void
    {
        $plugin = Plugin::factory()->enabled()->create();

        $this->assertTrue($plugin->is_enabled);
        $this->assertNotNull($plugin->enabled_at);
        $this->assertInstanceOf(Carbon::class, $plugin->enabled_at);
    }

    public function test_system_factory_state(): void
    {
        $plugin = Plugin::factory()->system()->create();

        $this->assertTrue($plugin->is_system);
    }
}
