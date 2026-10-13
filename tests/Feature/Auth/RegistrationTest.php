<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'password' => 'P@ssw0rd!23',
            'password_confirmation' => 'P@ssw0rd!23',
        ]);

        // Users are inactive by default and not auto-logged in
        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'is_active' => false,
        ]);
    }

    /** @return array<string, string> */
    private function payload(string $email, array $overrides = []): array
    {
        return array_merge([
            'name' => 'newcomer',
            'first_name' => 'New',
            'last_name' => 'Comer',
            'email' => $email,
            'password' => 'P@ssw0rd!23',
            'password_confirmation' => 'P@ssw0rd!23',
        ], $overrides);
    }

    public function test_errors_do_not_reveal_whether_an_address_has_an_account(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        // A wrong confirmation keeps the account from being created, the
        // remaining errors must be the same for a known and an unknown address
        $known = $this->post('/register', $this->payload('admin@example.com', ['password_confirmation' => 'other']));
        $unknown = $this->post('/register', $this->payload('nobody@example.com', ['password_confirmation' => 'other']));

        $known->assertSessionHasErrors('password')->assertSessionDoesntHaveErrors('email');
        $unknown->assertSessionHasErrors('password')->assertSessionDoesntHaveErrors('email');
    }

    public function test_taken_address_gets_the_normal_answer_without_an_account(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->post('/register', $this->payload('admin@example.com'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('auth.registration_pending'));

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertDatabaseMissing('users', ['name' => 'newcomer']);
    }
}
