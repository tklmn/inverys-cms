<?php

namespace Tests\Feature\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            // The link moves the token into the session and continues on the form
            $this->get('/reset-password/'.$notification->token)
                ->assertRedirect(route('password.reset.form'));

            $response = $this->get(route('password.reset.form'));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'P@ssw0rd!23',
                'password_confirmation' => 'P@ssw0rd!23',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_reset_password_revokes_api_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        ApiToken::create(['user_id' => $user->id, 'name' => 'Integration', 'token' => hash('sha256', 'x'), 'abilities' => ['read']]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'P@ssw0rd!23',
                'password_confirmation' => 'P@ssw0rd!23',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
    }
}
