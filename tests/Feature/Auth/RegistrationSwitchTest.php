<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration can be switched off (Security → Allow registration).
 */
class RegistrationSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->seedPermissions();
    }

    protected function tearDown(): void
    {
        // The settings cache is static — "off" must not leak into other tests
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    private function close(): void
    {
        Setting::updateOrCreate(['key' => 'security.registration_enabled'], ['value' => 'off']);
        EloquentSettingRepository::clearCache();
    }

    public function test_registration_is_open_by_default(): void
    {
        $this->get('/register')->assertOk();
        $this->get('/login')->assertSee(route('register'), false);
    }

    public function test_closed_registration_has_no_page_no_link_and_creates_no_account(): void
    {
        $this->close();

        $this->get('/register')->assertNotFound();
        $this->get('/login')->assertDontSee(route('register'), false);

        $this->post('/register', [
            'name' => 'spam',
            'first_name' => 'S',
            'last_name' => 'Pam',
            'email' => 'spam@example.com',
            'password' => 'Secret-Passw0rd!',
            'password_confirmation' => 'Secret-Passw0rd!',
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'spam@example.com']);
    }

    public function test_admin_can_switch_registration_off(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.security'))
            ->assertSee(__('translation.settings.security_registration_enabled'));

        $this->assertTrue(registration_open());
        $this->close();
        $this->assertFalse(registration_open());
    }
}
