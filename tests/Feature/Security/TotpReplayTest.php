<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A TOTP code is valid only once (RFC 6238, section 5.2): whoever reads a
 * code along (shoulder surfing, phishing proxy) must not reuse it for their
 * own sign-in within the same time window.
 */
class TotpReplayTest extends TestCase
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

    public function test_service_accepts_a_code_only_once(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateTotpSecret();
        $code = $this->currentCode($service, $secret);

        $this->assertTrue($service->verifyTotp($secret, $code));
        $this->assertFalse($service->verifyTotp($secret, $code));
    }

    public function test_used_code_cannot_pass_a_second_challenge(): void
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateTotpSecret();
        $user = User::factory()->create([
            'two_factor_method' => 'totp',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $code = $this->currentCode($service, $secret);

        // First sign-in with the code: passed
        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('two_factor_verified', $user->id);

        // Second session (attacker) with the same code: rejected
        $this->flushSession();
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
