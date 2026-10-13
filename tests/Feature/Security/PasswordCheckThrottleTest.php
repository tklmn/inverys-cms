<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Routes that check the current password are throttled — whoever has
 * hijacked someone else's session must not be able to brute-force the
 * password (confirm password, change password).
 */
class PasswordCheckThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    public function test_confirm_password_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post('/confirm-password', ['password' => 'wrong-'.$i])
                ->assertSessionHasErrors();
        }

        $this->actingAs($user)->post('/confirm-password', ['password' => 'wrong-7'])
            ->assertStatus(429);
    }

    public function test_password_update_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->from('/profile')->put('/password', [
                'current_password' => 'wrong-'.$i,
                'password' => 'New-Passw0rd!xyz',
                'password_confirmation' => 'New-Passw0rd!xyz',
            ])->assertRedirect('/profile');
        }

        $this->actingAs($user)->put('/password', [
            'current_password' => 'wrong-7',
            'password' => 'New-Passw0rd!xyz',
            'password_confirmation' => 'New-Passw0rd!xyz',
        ])->assertStatus(429);
    }
}
