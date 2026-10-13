<?php

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        // Re-enable 2FA for these tests
        Setting::updateOrCreate(
            ['key' => 'security.two_factor_enabled'],
            ['value' => 'on', 'type' => 'string', 'description' => '2FA enabled'],
        );
        Setting::firstOrCreate(
            ['key' => 'security.two_factor_code_lifetime'],
            ['value' => '10', 'type' => 'string', 'description' => 'Code lifetime'],
        );
    }

    public function test_login_redirects_to_two_factor_challenge(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_two_factor_challenge_page_is_displayed(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['two_factor_code_sent' => true])
            ->get(route('two-factor.challenge'));

        $response->assertOk();
        $response->assertSee(__('auth.two_factor.challenge_title'));
    }

    public function test_valid_email_code_verifies_and_redirects(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $code = $service->generateEmailCode($user);

        $response = $this->actingAs($user)
            ->post(route('two-factor.verify'), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('home'));
    }

    public function test_invalid_code_shows_error(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('two-factor.verify'), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();

        DB::table('two_factor_codes')->insert([
            'user_id' => $user->id,
            'code' => '123456',
            'expires_at' => now()->subMinutes(1),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('two-factor.verify'), [
                'code' => '123456',
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_resend_sends_new_code(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('two-factor.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_middleware_redirects_unverified_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/profile');

        $response->assertRedirect(route('two-factor.challenge'));
    }

    public function test_middleware_passes_verified_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->get('/profile');

        $response->assertOk();
    }

    public function test_middleware_passes_when_2fa_disabled(): void
    {
        Setting::updateOrCreate(
            ['key' => 'security.two_factor_enabled'],
            ['value' => 'off'],
        );

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_totp_setup_generates_secret(): void
    {
        $user = User::factory()->create();
        $emailCode = app(TwoFactorService::class)->generateEmailCode($user);

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.enable-totp'), ['code' => $emailCode]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('two_factor_secret');
        $response->assertSessionHas('two_factor_pending_secret');

        // Until confirmation the previous setting stays in place
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_totp_confirm_with_valid_code(): void
    {
        $user = User::factory()->create();
        $service = app(TwoFactorService::class);
        $secret = $service->generateTotpSecret();

        // Generate a valid TOTP code
        $secretBytes = $this->invokePrivateMethod($service, 'base32Decode', [$secret]);
        $counter = intdiv(time(), 30);
        $code = $this->invokePrivateMethod($service, 'generateOtp', [$secretBytes, $counter]);

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id, 'two_factor_pending_secret' => $secret])
            ->post(route('two-factor.confirm-totp'), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('two_factor_recovery_codes');
        $response->assertSessionMissing('two_factor_pending_secret');

        $user->refresh();
        $this->assertEquals('totp', $user->two_factor_method);
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_totp_disable_requires_valid_code(): void
    {
        $service = app(TwoFactorService::class);
        $user = User::factory()->create([
            'two_factor_method' => 'totp',
            'two_factor_secret' => $service->generateTotpSecret(),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.disable-totp'), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertEquals('totp', $user->two_factor_method);
    }

    public function test_totp_disable_with_valid_code(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateTotpSecret();
        $user = User::factory()->create([
            'two_factor_method' => 'totp',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        // Generate a valid TOTP code for the current time window
        $secretBytes = $this->invokePrivateMethod($service, 'base32Decode', [$secret]);
        $counter = intdiv(time(), 30);
        $code = $this->invokePrivateMethod($service, 'generateOtp', [$secretBytes, $counter]);

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.disable-totp'), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNull($user->two_factor_method);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_recovery_code_works(): void
    {
        $user = User::factory()->create([
            'two_factor_method' => 'totp',
            'two_factor_confirmed_at' => now(),
        ]);

        $service = app(TwoFactorService::class);
        $codes = $service->generateRecoveryCodes();
        $service->storeRecoveryCodes($user, $codes);

        $response = $this->actingAs($user)
            ->post(route('two-factor.verify'), [
                'code' => $codes[0],
                'use_recovery' => '1',
            ]);

        $response->assertRedirect(route('home'));
        $this->assertEquals(7, $service->remainingRecoveryCodes($user->refresh()));
    }

    public function test_logout_clears_two_factor_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    private function invokePrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }
}
