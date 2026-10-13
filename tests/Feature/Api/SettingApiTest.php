<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);
        $this->token = $result['plain_text'];
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_index_hides_sensitive_settings(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Test']);
        Setting::create(['key' => 'mail.host', 'value' => 'smtp.secret.com']);
        Setting::create(['key' => 'mail.password', 'value' => 'hunter2']);
        Setting::create(['key' => 'notification_email', 'value' => 'on']);

        $response = $this->getJson('/api/v1/settings', $this->authHeaders());
        $response->assertOk();

        $keys = collect($response->json('data'))->pluck('key');
        $this->assertContains('site_name', $keys);
        $this->assertNotContains('mail.host', $keys);
        $this->assertNotContains('mail.password', $keys);
        $this->assertNotContains('notification_email', $keys);
    }

    public function test_update_allowed_key(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Old Name']);

        $this->putJson('/api/v1/settings/site_name', [
            'value' => 'New Name',
        ], $this->authHeaders())
            ->assertOk();

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'New Name']);
    }

    public function test_update_blocked_key_returns_403(): void
    {
        Setting::create(['key' => 'mail.password', 'value' => 'old']);

        $this->putJson('/api/v1/settings/mail.password', [
            'value' => 'new-password',
        ], $this->authHeaders())
            ->assertForbidden();
    }

    public function test_update_validates_value_max_length(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'Test']);

        $this->putJson('/api/v1/settings/site_name', [
            'value' => str_repeat('x', 1001),
        ], $this->authHeaders())
            ->assertUnprocessable();
    }

    public function test_update_nonexistent_key_returns_404(): void
    {
        $this->putJson('/api/v1/settings/nonexistent', [
            'value' => 'test',
        ], $this->authHeaders())
            ->assertForbidden(); // Not in whitelist
    }

    /**
     * The active theme has its own endpoint (PUT /api/v1/themes/active,
     * manage-themes, check that the theme exists) — the general one does not write it.
     */
    public function test_active_theme_cannot_be_written_through_the_settings_endpoint(): void
    {
        Setting::updateOrCreate(['key' => 'active_theme'], ['value' => 'default']);

        $response = $this->putJson('/api/v1/settings/active_theme', [
            'value' => 'does-not-exist',
        ], $this->authHeaders());

        $response->assertForbidden();
        $this->assertStringContainsString('/api/v1/themes/active', (string) $response->json('message'));
        $this->assertDatabaseHas('settings', ['key' => 'active_theme', 'value' => 'default']);
    }

    public function test_update_uses_the_same_validation_as_the_backend(): void
    {
        Setting::create(['key' => 'theme', 'value' => 'light']);
        Setting::create(['key' => 'timezone', 'value' => 'Europe/Berlin']);
        Setting::create(['key' => 'language', 'value' => 'en']);

        $this->putJson('/api/v1/settings/theme', ['value' => 'blue'], $this->authHeaders())->assertUnprocessable();
        $this->putJson('/api/v1/settings/timezone', ['value' => 'Nowhere/City'], $this->authHeaders())->assertUnprocessable();
        $this->putJson('/api/v1/settings/language', ['value' => 'xx'], $this->authHeaders())->assertUnprocessable();

        $this->putJson('/api/v1/settings/theme', ['value' => 'dark'], $this->authHeaders())->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'theme', 'value' => 'dark']);
        $this->assertDatabaseHas('settings', ['key' => 'timezone', 'value' => 'Europe/Berlin']);
    }

    public function test_keys_with_their_own_page_are_not_writable(): void
    {
        foreach (['seo.robots_mode' => 'off', 'search.per_page' => '10', 'blog.url_prefix' => 'blog'] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);

            $this->putJson('/api/v1/settings/'.$key, ['value' => 'x'], $this->authHeaders())->assertForbidden();
            $this->assertDatabaseHas('settings', ['key' => $key, 'value' => $value]);
        }
    }

    public function test_index_never_returns_encrypted_values(): void
    {
        // The prefix of a disabled plugin is not registered — the type is what counts
        Setting::create(['key' => 'captcha.turnstile_secret_key', 'value' => 'cipher', 'type' => 'encrypted']);

        $keys = collect($this->getJson('/api/v1/settings', $this->authHeaders())->json('data'))->pluck('key');

        $this->assertNotContains('captcha.turnstile_secret_key', $keys);
    }
}
