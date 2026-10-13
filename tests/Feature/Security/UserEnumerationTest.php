<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The "forgot password" form must not reveal whether an account exists for
 * an address — neither through the message nor through the throttling of a
 * second link.
 */
class UserEnumerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    public function test_unknown_and_known_addresses_get_the_same_answer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        foreach ([$known, $unknown] as $response) {
            $response->assertRedirect('/forgot-password');
            $response->assertSessionHasNoErrors();
            $response->assertSessionHas('status', __('passwords.sent'));
        }

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_throttled_second_request_looks_like_a_normal_answer(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'known@example.com']);

        $this->post('/forgot-password', ['email' => 'known@example.com']);

        // Second link within the lock period: the broker reports "throttled"
        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'known@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }
}
