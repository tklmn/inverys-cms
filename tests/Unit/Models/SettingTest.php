<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_setting(): void
    {
        $setting = Setting::factory()->create([
            'key' => 'site_name',
            'value' => 'My CMS',
            'type' => 'string',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'site_name',
            'value' => 'My CMS',
        ]);
        $this->assertEquals('site_name', $setting->key);
        $this->assertEquals('My CMS', $setting->value);
    }

    public function test_can_update_value(): void
    {
        $setting = Setting::factory()->create([
            'key' => 'language',
            'value' => 'en',
        ]);

        $setting->update(['value' => 'de']);
        $setting->refresh();

        $this->assertEquals('de', $setting->value);
        $this->assertDatabaseHas('settings', [
            'key' => 'language',
            'value' => 'de',
        ]);
    }

    public function test_soft_delete_works(): void
    {
        $setting = Setting::factory()->create();
        $setting->delete();

        $this->assertSoftDeleted('settings', ['id' => $setting->id]);
        $this->assertNull(Setting::find($setting->id));
        $this->assertNotNull(Setting::withTrashed()->find($setting->id));
    }
}
