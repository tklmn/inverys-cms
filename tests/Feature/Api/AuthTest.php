<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->disableTwoFactor();
    }

    public function test_login_returns_token(): void
    {
        $user = $this->createAdmin();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_requires_two_factor_when_enabled(): void
    {
        // Re-enable 2FA globally (setUp disables it for the other cases).
        Setting::where('key', 'security.two_factor_enabled')->update(['value' => 'on']);

        $user = $this->createAdmin();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(202)
            ->assertJson(['two_factor_required' => true])
            ->assertJsonMissing(['token' => true]);

        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->createAdmin();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $user = $this->createAdmin(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_authenticated_request_with_valid_token(): void
    {
        $user = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_request_with_expired_token_fails(): void
    {
        $user = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($user, [
            'expires_at' => now()->subHour(),
        ]);

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertUnauthorized();
    }

    public function test_request_without_token_fails(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_deletes_token(): void
    {
        $user = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertOk();

        $this->assertDatabaseMissing('api_tokens', ['id' => $result['token']->id]);
    }

    public function test_read_only_token_can_read_but_not_write(): void
    {
        $user = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($user, ['abilities' => ['read']]);
        $headers = ['Authorization' => 'Bearer '.$result['plain_text']];

        $this->getJson('/api/v1/pages', $headers)->assertOk();

        $this->postJson('/api/v1/pages', ['title' => 'Blocked'], $headers)
            ->assertStatus(403);
    }

    public function test_full_token_can_write(): void
    {
        $user = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($user); // abilities: ['*']

        $this->postJson('/api/v1/pages', ['title' => 'Scoped Page'], [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertStatus(201);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->createAdmin();

        // Make 6 login attempts (limit is 5 per minute)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_login_uses_the_account_lockout_with_the_correct_password_too(): void
    {
        Setting::updateOrCreate(['key' => 'security.login_max_attempts'], ['value' => '3']);
        $user = $this->createAdmin();

        // Without the per-IP middleware throttle: the lockout itself has to kick in
        $this->withoutMiddleware(ThrottleRequests::class);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(401);
        }

        // Locked — even the correct password no longer helps then
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }
}
