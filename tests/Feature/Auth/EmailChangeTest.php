<?php

namespace Tests\Feature\Auth;

use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangedNoticeMail;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\EmailChange;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Changing the email address: password, confirmation to the new address,
 * "That wasn't me" to the old one.
 */
class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->seedPermissions();
        Mail::fake();
    }

    private function profilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'first_name' => 'Max',
            'last_name' => 'Doe',
            'email' => $user->email,
        ], $overrides);
    }

    private function requestChange(User $user, string $new = 'new@example.com'): string
    {
        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, ['email' => $new, 'current_password' => 'password']))
            ->assertSessionHasNoErrors();

        $url = null;
        Mail::assertSent(EmailChangeConfirmMail::class, function ($mail) use ($new, &$url) {
            $url = $mail->url;

            return $mail->hasTo($new);
        });

        return $url;
    }

    public function test_new_address_needs_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, ['email' => 'new@example.com']))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, ['email' => 'new@example.com', 'current_password' => 'wrong']))
            ->assertSessionHasErrors('current_password');

        $this->assertSame('old@example.com', $user->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_other_profile_fields_save_without_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, ['first_name' => 'New']))
            ->assertSessionHasNoErrors();

        $this->assertSame('New', $user->fresh()->first_name);
    }

    public function test_address_changes_only_after_confirmation_and_old_address_is_told(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user);

        // Until confirmation the old address applies
        $this->assertSame('old@example.com', $user->fresh()->email);

        // The link only shows a page — only the button makes the change
        $this->get($url)->assertOk()->assertSee('new@example.com');
        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->post($url)->assertRedirect();
        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNull(EmailChange::pending($user->fresh()));

        Mail::assertSent(EmailChangedNoticeMail::class, fn ($mail) => $mail->hasTo('old@example.com') && $mail->newEmail === 'new@example.com');

        // A second time does not work
        $this->post($url)->assertSessionHas('error');
    }

    public function test_that_was_not_me_restores_the_address_and_locks_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        ApiToken::create(['user_id' => $user->id, 'name' => 'x', 'token' => hash('sha256', 'y'), 'abilities' => ['read']]);
        $this->post($this->requestChange($user));

        $revertUrl = null;
        Mail::assertSent(EmailChangedNoticeMail::class, function ($mail) use (&$revertUrl) {
            $revertUrl = $mail->url;

            return true;
        });

        $this->get($revertUrl)->assertOk();
        $this->post($revertUrl)->assertRedirect(route('login'));

        $user->refresh();
        $this->assertSame('old@example.com', $user->email);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_discarded_or_tampered_links_do_nothing(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $url = $this->requestChange($user);

        $this->post(preg_replace('/signature=[0-9a-f]/', 'signature=x', $url))->assertForbidden();

        $this->actingAs($user)->post(route('email-change.cancel'))->assertRedirect(route('profile.edit'));
        $this->post($url)->assertSessionHas('error');
        $this->assertSame('old@example.com', $user->fresh()->email);
    }
}
