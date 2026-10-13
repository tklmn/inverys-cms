<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Shared layout and partials of the auth pages (backend/layouts/guest,
 * auth/partials).
 */
class AuthScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_the_shared_layout_and_password_partial(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('auth-page', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee(__('translation.auth.login_title'))
            ->assertSee(route('password.request'), false)
            ->assertSee(route('home'), false);
    }

    public function test_register_shows_both_password_fields(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('data-password-toggle="password_confirmation"', false)
            ->assertSee(__('translation.auth.register_title'));
    }

    public function test_forgot_password_links_back_to_login(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee(__('translation.auth.back_to_login'))
            ->assertSee(route('login'), false);
    }

    public function test_failed_login_shows_the_error_at_the_field(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->from('/login')->post('/login', ['email' => 'a@example.com', 'password' => 'wrong'])->assertRedirect('/login');

        $this->get('/login')->assertOk()->assertSee('ui-error', false)->assertSee('data-invalid', false);
    }

    public function test_remember_switch_off_does_not_remember(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'off']);

        $this->assertAuthenticated();
        $response->assertCookieMissing(Auth::guard()->getRecallerName());
    }
}
