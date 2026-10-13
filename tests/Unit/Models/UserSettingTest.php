<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_user_setting(): void
    {
        $user = User::factory()->create();

        $setting = UserSetting::create([
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);
        $this->assertEquals('theme', $setting->key);
        $this->assertEquals('dark', $setting->value);
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();

        $setting = UserSetting::create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'de',
        ]);

        $this->assertInstanceOf(User::class, $setting->user);
        $this->assertEquals($user->id, $setting->user->id);
    }

    public function test_can_update_value(): void
    {
        $user = User::factory()->create();

        $setting = UserSetting::create([
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'light',
        ]);

        $setting->update(['value' => 'dark']);
        $setting->refresh();

        $this->assertEquals('dark', $setting->value);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);
    }
}
