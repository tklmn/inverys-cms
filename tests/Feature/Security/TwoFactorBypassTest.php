<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 2FA setup routes must not be reachable before the challenge is passed —
 * otherwise someone with a stolen password turns 2FA off or guesses codes.
 */
class TwoFactorBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        Setting::updateOrCreate(
            ['key' => 'security.two_factor_enabled'],
            ['value' => 'on', 'type' => 'string', 'description' => '2FA enabled'],
        );
    }

    protected function tearDown(): void
    {
        // The settings cache is static — otherwise 2FA would stay enabled
        // for the following test classes
        EloquentSettingRepository::clearCache();
        parent::tearDown();
    }

    public function test_unverified_session_cannot_disable_two_factor(): void
    {
        $user = User::factory()->create();
        $code = app(TwoFactorService::class)->generateEmailCode($user);

        $this->actingAs($user)
            ->post(route('two-factor.toggle'), ['code' => $code])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertNotSame('1', $user->fresh()->getSetting('two_factor_disabled'));
    }

    public function test_unverified_session_cannot_reach_totp_management(): void
    {
        $user = User::factory()->create();

        foreach (['two-factor.enable-totp', 'two-factor.disable-totp', 'two-factor.regenerate-codes', 'two-factor.send-code'] as $route) {
            $this->actingAs($user)
                ->post(route($route), ['code' => '123456'])
                ->assertRedirect(route('two-factor.challenge'));
        }

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_challenge_routes_remain_reachable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['two_factor_code_sent' => true])
            ->get(route('two-factor.challenge'))
            ->assertOk();
    }

    public function test_two_factor_setup_code_checks_are_throttled(): void
    {
        $user = User::factory()->create();
        app(TwoFactorService::class)->generateEmailCode($user);

        $statuses = [];
        for ($i = 0; $i < 12; $i++) {
            $statuses[] = $this->actingAs($user)
                ->withSession(['two_factor_verified' => $user->id])
                ->post(route('two-factor.toggle'), ['code' => str_pad((string) $i, 6, '0', STR_PAD_LEFT)])
                ->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    // --- Switching factors only with the current factor ---------------------

    public function test_active_totp_cannot_be_replaced_from_a_hijacked_session(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateTotpSecret();
        $user = User::factory()->create([
            'two_factor_method' => 'totp',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $service->storeRecoveryCodes($user, $service->generateRecoveryCodes());
        $recovery = $user->fresh()->two_factor_recovery_codes;

        // An active app is not replaced via enable-totp
        $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.enable-totp'))
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('two_factor_pending_secret');

        $user->refresh();
        $this->assertSame($secret, $user->two_factor_secret);
        $this->assertSame('totp', $user->two_factor_method);
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertSame($recovery, $user->two_factor_recovery_codes);
    }

    public function test_switch_from_email_to_totp_requires_the_email_code(): void
    {
        $service = app(TwoFactorService::class);
        $user = User::factory()->create();

        // Hijacked session without access to the mailbox
        $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.enable-totp'), ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('two_factor_pending_secret');

        // confirm-totp without a started setup binds no foreign app either
        $attackerSecret = $service->generateTotpSecret();
        $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.confirm-totp'), ['code' => $this->currentCode($service, $attackerSecret)])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTotpEnabled());

        // The setup starts with the code from the email
        $emailCode = $service->generateEmailCode($user);
        $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('two-factor.enable-totp'), ['code' => $emailCode])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('two_factor_pending_secret');
    }

    // --- Failed attempts per account, not per IP ----------------------------

    public function test_api_second_factor_cannot_be_guessed_from_many_addresses(): void
    {
        $this->seedPermissions();
        $user = $this->createAdmin();
        $service = app(TwoFactorService::class);

        // Only one attempt per address — so the per-IP lock never kicks in
        for ($i = 1; $i <= TwoFactorService::MAX_FAILED_ATTEMPTS; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "2001:db8::{$i}"])
                ->postJson('/api/v1/auth/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'two_factor_code' => '000000',
                ])->assertStatus(401);
        }

        // After that the account is locked — even the right code from a new address
        $code = $service->generateEmailCode($user);
        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::99'])
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
                'two_factor_code' => $code,
            ])->assertStatus(429)
            ->assertJsonMissingPath('token');

        // Sign-in in the browser shares the same budget
        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('two_factor_verified');
    }

    private function currentCode(TwoFactorService $service, string $secret): string
    {
        $decode = new \ReflectionMethod($service, 'base32Decode');
        $otp = new \ReflectionMethod($service, 'generateOtp');

        return $otp->invoke($service, $decode->invoke($service, $secret), intdiv(time(), 30));
    }
}
