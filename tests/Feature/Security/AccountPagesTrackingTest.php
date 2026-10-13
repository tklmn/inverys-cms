<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Models\Page;
use App\Models\Plugin;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\PluginManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Account pages (login, password reset, email change, profile) carry tokens
 * in their address and passwords in their forms. Consent services such as
 * analytics record the full page URL, and their reports are often readable by
 * people without a CMS account — so no service script and no plugin hook may
 * run there, and the reset token must not stay in the address at all.
 */
class AccountPagesTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        Plugin::create(['slug' => 'cookie-consent', 'name' => 'Cookie Consent', 'version' => '1.0.0', 'is_enabled' => true, 'is_system' => true]);
        app(PluginManager::class)->boot();
        app('router')->getRoutes()->refreshNameLookups();

        Page::factory()->create(['title' => 'Home', 'slug' => '/', 'is_root' => true, 'is_active' => true]);
        Setting::updateOrCreate(['key' => 'cc.services'], ['value' => json_encode([
            [
                'id' => 'ga', 'name' => 'GA', 'category' => 'statistics', 'position' => 'head',
                'script' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1"></script>',
                'hosts' => ['https://www.googletagmanager.com'],
            ],
            [
                'id' => 'pixel', 'name' => 'Pixel', 'category' => 'marketing', 'position' => 'body_end',
                'script' => '<script src="https://pixel.example.com/track.js"></script>',
                'hosts' => ['https://pixel.example.com'],
            ],
        ]), 'type' => 'plugin_setting']);
        EloquentSettingRepository::clearCache();
    }

    protected function tearDown(): void
    {
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    private function withConsent(): static
    {
        return $this->withUnencryptedCookie('cc_consent', json_encode(['ts' => time(), 'statistics' => true, 'marketing' => true]));
    }

    private function assertNoServiceScripts($response): void
    {
        $html = $response->getContent();
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('googletagmanager', $html);
        $this->assertStringNotContainsString('pixel.example.com', $html);
        $this->assertStringNotContainsString('googletagmanager', $csp);
        $this->assertStringNotContainsString('pixel.example.com', $csp);
    }

    public function test_website_pages_still_get_the_service_scripts(): void
    {
        $response = $this->withConsent()->get('/');

        $response->assertOk();
        $this->assertStringContainsString('googletagmanager.com/gtag', $response->getContent());
        $this->assertStringContainsString('pixel.example.com/track.js', $response->getContent());
    }

    public function test_reset_link_moves_the_token_out_of_the_address(): void
    {
        $response = $this->withConsent()->get('/reset-password/SECRETTOKEN?email=admin@example.com');

        $response->assertRedirect(route('password.reset.form'));
        $response->assertSessionHas(NewPasswordController::SESSION_KEY, ['token' => 'SECRETTOKEN', 'email' => 'admin@example.com']);
        $this->assertStringNotContainsString('SECRETTOKEN', $response->headers->get('Location'));
        $this->assertNoServiceScripts($response);
    }

    public function test_reset_form_runs_no_service_scripts(): void
    {
        $response = $this->withConsent()
            ->withSession([NewPasswordController::SESSION_KEY => ['token' => 'SECRETTOKEN', 'email' => 'admin@example.com']])
            ->get(route('password.reset.form'));

        $response->assertOk();
        $this->assertStringContainsString('value="SECRETTOKEN"', $response->getContent());
        $this->assertNoServiceScripts($response);
    }

    public function test_reset_form_without_a_link_goes_back_to_forgot_password(): void
    {
        $this->get(route('password.reset.form'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_password_can_be_reset_through_the_link_and_the_session_is_cleared(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertRedirect(route('password.reset.form'));

            $this->get(route('password.reset.form'))
                ->assertOk()
                ->assertSee($notification->token)
                ->assertSee($user->email);

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'P@ssw0rd!23',
                'password_confirmation' => 'P@ssw0rd!23',
            ])->assertSessionHasNoErrors()
                ->assertRedirect(route('login'))
                ->assertSessionMissing(NewPasswordController::SESSION_KEY);

            return true;
        });
    }

    public function test_guest_auth_pages_run_no_service_scripts(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $response = $this->withConsent()->get($path);

            $response->assertOk();
            $this->assertNoServiceScripts($response);
        }
    }

    public function test_email_change_links_run_no_service_scripts(): void
    {
        $user = User::factory()->create();

        $confirm = URL::temporarySignedRoute('email-change.confirm', now()->addHour(), ['user' => $user->id, 'nonce' => 'SECRETNONCE']);
        $revert = URL::temporarySignedRoute('email-change.revert', now()->addHour(), ['user' => $user->id, 'nonce' => 'SECRETNONCE']);

        foreach ([$confirm, $revert] as $url) {
            $response = $this->withConsent()->get($url);

            $response->assertOk();
            $this->assertNoServiceScripts($response);
        }
    }

    public function test_profile_runs_no_service_scripts(): void
    {
        $user = User::factory()->create();

        $response = $this->withConsent()->actingAs($user)->get('/profile');

        $response->assertOk();
        $this->assertNoServiceScripts($response);
    }
}
