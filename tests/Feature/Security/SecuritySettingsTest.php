<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_security_settings(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.security'));

        $response->assertStatus(200);
    }

    public function test_editor_cannot_view_security_settings(): void
    {
        $response = $this->actingAs($this->createEditor())
            ->get(route('backend.settings.security'));

        $response->assertStatus(403);
    }

    public function test_admin_can_save_security_settings(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), [
                'password_min_length' => 12,
                'password_require_uppercase' => 'on',
                'password_require_numbers' => 'on',
                'password_require_symbols' => 'off',
                'login_max_attempts' => 10,
                'login_lockout_duration' => 5,
                'two_factor_enabled' => 'on',
                'two_factor_code_lifetime' => 10,
                'api_rate_limit' => 100,
                'session_lifetime' => 60,
                'notification_retention_days' => 14,
                'activity_log_retention_days' => 30,
            ]);

        $response->assertRedirect(route('backend.settings.security'));
        $response->assertSessionHas('success');

        $this->assertEquals('12', Setting::where('key', 'security.password_min_length')->value('value'));
        $this->assertEquals('off', Setting::where('key', 'security.password_require_symbols')->value('value'));
        $this->assertEquals('100', Setting::where('key', 'security.api_rate_limit')->value('value'));
        $this->assertEquals('14', Setting::where('key', 'security.notification_retention_days')->value('value'));
    }

    public function test_password_min_length_cannot_go_below_8(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), [
                'password_min_length' => 4,
                'password_require_uppercase' => 'on',
                'password_require_numbers' => 'on',
                'password_require_symbols' => 'on',
                'login_max_attempts' => 5,
                'login_lockout_duration' => 1,
                'api_rate_limit' => 60,
                'session_lifetime' => 120,
                'notification_retention_days' => 30,
                'activity_log_retention_days' => 90,
            ]);

        $response->assertSessionHasErrors('password_min_length');
    }

    public function test_api_rate_limit_cannot_go_below_10(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), [
                'password_min_length' => 10,
                'password_require_uppercase' => 'on',
                'password_require_numbers' => 'on',
                'password_require_symbols' => 'on',
                'login_max_attempts' => 5,
                'login_lockout_duration' => 1,
                'api_rate_limit' => 5,
                'session_lifetime' => 120,
                'notification_retention_days' => 30,
                'activity_log_retention_days' => 90,
            ]);

        $response->assertSessionHasErrors('api_rate_limit');
    }

    public function test_login_max_attempts_cannot_go_below_3(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), [
                'password_min_length' => 10,
                'password_require_uppercase' => 'on',
                'password_require_numbers' => 'on',
                'password_require_symbols' => 'on',
                'login_max_attempts' => 1,
                'login_lockout_duration' => 1,
                'api_rate_limit' => 60,
                'session_lifetime' => 120,
                'notification_retention_days' => 30,
                'activity_log_retention_days' => 90,
            ]);

        $response->assertSessionHasErrors('login_max_attempts');
    }

    public function test_session_lifetime_cannot_go_below_5(): void
    {
        $response = $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), [
                'password_min_length' => 10,
                'password_require_uppercase' => 'on',
                'password_require_numbers' => 'on',
                'password_require_symbols' => 'on',
                'login_max_attempts' => 5,
                'login_lockout_duration' => 1,
                'api_rate_limit' => 60,
                'session_lifetime' => 2,
                'notification_retention_days' => 30,
                'activity_log_retention_days' => 90,
            ]);

        $response->assertSessionHasErrors('session_lifetime');
    }
}
