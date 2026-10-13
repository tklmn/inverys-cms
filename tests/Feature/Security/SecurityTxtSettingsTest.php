<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * security.txt is maintained under Settings → Security; empty by default (404).
 */
class SecurityTxtSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        config(['cms.security_txt.contact' => '']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'password_min_length' => 10,
            'password_require_uppercase' => 'on',
            'password_require_numbers' => 'on',
            'password_require_symbols' => 'on',
            'login_max_attempts' => 5,
            'login_lockout_duration' => 1,
            'two_factor_enabled' => 'on',
            'two_factor_code_lifetime' => 10,
            'api_rate_limit' => 60,
            'session_lifetime' => 120,
            'notification_retention_days' => 30,
        ], $overrides);
    }

    public function test_default_is_empty_and_no_file_is_served(): void
    {
        $this->get('/.well-known/security.txt')->assertNotFound();

        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.security'))
            ->assertOk()
            ->assertSee(__('translation.settings.security_txt_off'));
    }

    public function test_saved_contact_is_published(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.settings.security.save'), $this->payload([
                'txt_contact' => "security@example.com\nhttps://example.com/report",
                'txt_policy' => 'https://example.com/policy',
                'txt_languages' => 'de, en',
                'txt_expires_days' => 90,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame("security@example.com\nhttps://example.com/report", Setting::where('key', 'security.txt_contact')->value('value'));

        // Fetch like a visitor (without the admin session)
        $this->app['auth']->forgetGuards();
        $body = $this->get('/.well-known/security.txt')->assertOk()->getContent();
        $this->assertStringContainsString("Contact: mailto:security@example.com\n", $body);
        $this->assertStringContainsString("Contact: https://example.com/report\n", $body);
        $this->assertStringContainsString('Policy: https://example.com/policy', $body);
        $this->assertStringContainsString('Preferred-Languages: de, en', $body);
        $this->assertStringContainsString('Expires: '.now('UTC')->startOfDay()->addDays(90)->format('Y-m-d'), $body);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $admin = $this->createAdmin();

        foreach ([
            ['txt_contact' => "security@example.com\njavascript:alert(1)"],
            ['txt_policy' => 'http://example.com/policy'],
            ['txt_encryption' => 'ftp://example.com/key'],
            ['txt_languages' => 'de, <script>'],
            ['txt_expires_days' => 7],
        ] as $bad) {
            $this->actingAs($admin)
                ->post(route('backend.settings.security.save'), $this->payload($bad))
                ->assertSessionHasErrors(array_key_first($bad));
        }
    }

    /**
     * The .env is the fallback for empty fields — the page has to say so,
     * otherwise "empty contact = no file" would be misleading.
     */
    public function test_env_fallbacks_are_shown_and_a_fixed_expiry_locks_the_period(): void
    {
        config([
            'cms.security_txt.contact' => 'env@example.com',
            'cms.security_txt.expires' => '2027-01-31',
        ]);
        Setting::create(['key' => 'security.txt_expires_days', 'value' => '90']);
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.settings.security'))
            ->assertOk()
            ->assertSee(__('translation.settings.security_txt_env_fallback', ['value' => 'env@example.com']))
            ->assertSee(__('translation.settings.security_txt_expires_env', ['date' => '2027-01-31']));

        // A locked field is not sent — the previous choice stays
        $this->actingAs($admin)
            ->post(route('backend.settings.security.save'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('90', Setting::where('key', 'security.txt_expires_days')->value('value'));
    }

    public function test_editor_cannot_change_it(): void
    {
        $this->actingAs($this->createEditor())
            ->post(route('backend.settings.security.save'), $this->payload(['txt_contact' => 'evil@example.com']))
            ->assertForbidden();
    }
}
