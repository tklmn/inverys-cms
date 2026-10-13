<?php

namespace Tests\Feature\Security;

use App\Models\ApiToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cms\EloquentSettingRepository;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A passed 2FA challenge only counts for the account that passed it.
 * The flag must not survive the next login in the same session: otherwise
 * whoever deleted their own account could sign in with the password of
 * another account (even an admin's) without a second factor.
 */
class TwoFactorSessionBindingTest extends TestCase
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

    public function test_challenge_passed_by_another_account_does_not_count(): void
    {
        $attacker = User::factory()->create();
        $victim = User::factory()->create();

        // Session in which the attacker's (since deleted) account passed
        // the challenge — the victim is signed in now
        $this->actingAs($victim)
            ->withSession(['two_factor_verified' => $attacker->id])
            ->get(route('profile.edit'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_unbound_flag_does_not_count(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['two_factor_verified' => true])
            ->get(route('profile.edit'))
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_login_discards_two_factor_state_of_the_session(): void
    {
        Mail::fake();
        $attacker = User::factory()->create();
        $victim = User::factory()->create();

        $this->withSession(['two_factor_verified' => $attacker->id, 'two_factor_code_sent' => true])
            ->post('/login', ['email' => $victim->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'))
            ->assertSessionMissing('two_factor_verified');
    }

    public function test_passed_challenge_is_bound_to_the_account(): void
    {
        $user = User::factory()->create();
        $code = app(TwoFactorService::class)->generateEmailCode($user);

        $this->actingAs($user)
            ->post(route('two-factor.verify'), ['code' => $code])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('two_factor_verified', $user->id);

        $this->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_deleting_an_account_ends_its_sessions_and_tokens(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([[$user, 'deleted-user'], [$other, 'other-user']] as [$owner, $id]) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);
        }
        ApiToken::createToken($user, 'cli');
        $oldRememberToken = $user->remember_token;

        $user->delete();

        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user']);
        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
        $this->assertNotSame($oldRememberToken, User::withTrashed()->find($user->id)->remember_token);
    }

    public function test_restoring_an_account_does_not_revive_old_tokens(): void
    {
        $user = User::factory()->create();
        $user->delete();
        // Token from before this rule (not removed on deletion)
        ApiToken::createToken($user, 'old');

        $user->restore();

        $this->assertSame(0, ApiToken::where('user_id', $user->id)->count());
    }
}
