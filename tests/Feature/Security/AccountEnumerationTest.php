<?php

namespace Tests\Feature\Security;

use App\Mail\EmailChangeConfirmMail;
use App\Models\User;
use App\Support\EmailChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Which addresses have an account stays hidden: neither the API login, the
 * profile form, "forgot password" nor the reset form may tell them apart, not
 * even through the response time.
 */
class AccountEnumerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->disableTwoFactor();
    }

    /** A hasher that counts the password hashes it actually computes */
    private function countingHasher(): BcryptHasher
    {
        return new class(['rounds' => 4]) extends BcryptHasher
        {
            public int $hashes = 0;

            public function make(#[\SensitiveParameter] $value, array $options = [])
            {
                $this->hashes++;

                return parent::make($value, $options);
            }

            public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
            {
                if (is_string($hashedValue) && $hashedValue !== '') {
                    $this->hashes++;
                }

                return parent::check($value, $hashedValue, $options);
            }
        };
    }

    public function test_api_login_hashes_the_password_for_unknown_addresses_too(): void
    {
        User::factory()->create(['email' => 'known@example.com']);
        $hasher = $this->countingHasher();
        Hash::swap($hasher);

        $this->postJson('/api/v1/auth/login', ['email' => 'known@example.com', 'password' => 'wrong'])
            ->assertStatus(401)->assertJson(['message' => 'Invalid credentials.']);
        $known = $hasher->hashes;

        $hasher->hashes = 0;
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(401)->assertJson(['message' => 'Invalid credentials.']);

        // The same amount of hashing work, so the timing is alike
        $this->assertSame(1, $known);
        $this->assertSame($known, $hasher->hashes);
    }

    public function test_profile_form_does_not_tell_whether_an_address_is_taken(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);

        $payload = fn (string $email) => [
            'name' => $user->name,
            'first_name' => 'Max',
            'last_name' => 'Doe',
            'email' => $email,
            'current_password' => 'password',
        ];

        // A free and a taken address get the same answer
        foreach (['free@example.com', 'taken@example.com'] as $email) {
            $this->actingAs($user)->from('/profile')->patch(route('profile.update'), $payload($email))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'email-change-sent');
            $this->assertSame($email, EmailChange::pending($user->fresh())['email']);
        }

        // Only the free address gets a confirmation link; the taken one stays with its owner
        Mail::assertSent(EmailChangeConfirmMail::class, fn ($mail) => $mail->hasTo('free@example.com'));
        Mail::assertNotSent(EmailChangeConfirmMail::class, fn ($mail) => $mail->hasTo('taken@example.com'));
        $this->assertNull(EmailChange::canConfirm($user->fresh(), EmailChange::pending($user->fresh())['nonce']));
        $this->assertSame('me@example.com', $user->fresh()->email);
    }

    public function test_profile_form_does_not_tell_whether_an_address_is_reserved(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['email' => 'now@example.com']);
        $owner->setSetting(EmailChange::REVERT_SETTING, json_encode([
            'email' => 'held@example.com',
            'nonce' => 'n',
            'until' => now()->addDay()->getTimestamp(),
        ]));
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->patch(route('profile.update'), [
            'name' => $user->name,
            'first_name' => 'Max',
            'last_name' => 'Doe',
            'email' => 'held@example.com',
            'current_password' => 'password',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'email-change-sent');

        Mail::assertNothingSent();
        $this->assertNull(EmailChange::canConfirm($user->fresh(), EmailChange::pending($user->fresh())['nonce']));
    }

    public function test_profile_update_is_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->from('/profile')->patch(route('profile.update'), [
                'name' => $user->name,
                'first_name' => 'Max',
                'last_name' => 'Doe',
                'email' => 'other'.$i.'@example.com',
                'current_password' => 'wrong-'.$i,
            ])->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'first_name' => 'Max',
            'last_name' => 'Doe',
            'email' => 'other@example.com',
            'current_password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_forgot_password_answers_alike_when_sending_fails(): void
    {
        User::factory()->create(['email' => 'known@example.com']);
        Event::listen(NotificationSending::class, fn () => throw new RuntimeException('Mail server unreachable'));

        foreach (['known@example.com', 'nobody@example.com'] as $email) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => $email])
                ->assertRedirect('/forgot-password')
                ->assertSessionHas('status', __('passwords.sent'));
        }
    }

    public function test_reset_password_form_answers_alike_for_unknown_addresses(): void
    {
        $user = User::factory()->create(['email' => 'known@example.com']);
        $oldHash = $user->password;

        $errors = [];
        foreach (['known@example.com', 'nobody@example.com'] as $email) {
            $response = $this->from('/reset-password/wrong-token')->post('/reset-password', [
                'token' => 'wrong-token',
                'email' => $email,
                'password' => 'Str0ng!Passw0rd#2026',
                'password_confirmation' => 'Str0ng!Passw0rd#2026',
            ]);

            $response->assertRedirect('/reset-password/wrong-token')->assertSessionHasErrors('email');
            $errors[$email] = session('errors')->first('email');
        }

        // A wrong token for an existing account reads exactly like an unknown address
        $this->assertSame(__('passwords.token'), $errors['known@example.com']);
        $this->assertSame($errors['known@example.com'], $errors['nobody@example.com']);
        $this->assertSame($oldHash, $user->fresh()->password);
    }
}
