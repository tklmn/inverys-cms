<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * After a password change nobody may stay signed in with the old
 * password — other sessions end, the current one stays.
 */
class PasswordChangeSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    private function addSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }

    public function test_other_sessions_end_and_the_current_one_stays(): void
    {
        $user = User::factory()->create();
        $this->addSession($user, 'current-session');
        $this->addSession($user, 'stolen-session');
        $oldToken = $user->remember_token;

        $user->endOtherSessions('current-session');

        $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
    }

    public function test_without_a_kept_session_all_end(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->addSession($user, 'a');
        $this->addSession($user, 'b');
        $this->addSession($other, 'foreign');

        $user->endOtherSessions();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign']);
    }
}
