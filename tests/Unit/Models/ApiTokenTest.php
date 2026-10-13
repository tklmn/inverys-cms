<?php

namespace Tests\Unit\Models;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_expired_when_past(): void
    {
        $token = ApiToken::factory()->expired()->create();
        $this->assertTrue($token->isExpired());
    }

    public function test_not_expired_when_future(): void
    {
        $token = ApiToken::factory()->create(['expires_at' => now()->addDay()]);
        $this->assertFalse($token->isExpired());
    }

    public function test_not_expired_when_null(): void
    {
        $token = ApiToken::factory()->create(['expires_at' => null]);
        $this->assertFalse($token->isExpired());
    }

    public function test_has_ability_wildcard(): void
    {
        $token = ApiToken::factory()->create(['abilities' => ['*']]);
        $this->assertTrue($token->hasAbility('anything'));
    }

    public function test_has_ability_specific(): void
    {
        $token = ApiToken::factory()->create(['abilities' => ['read', 'write']]);
        $this->assertTrue($token->hasAbility('read'));
        $this->assertFalse($token->hasAbility('delete'));
    }

    public function test_create_token_hashes_and_returns_plain(): void
    {
        $user = User::factory()->create();
        $result = ApiToken::createToken($user, 'test-token', ['*'], 1440);

        $this->assertArrayHasKey('plain_text', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertNotEquals($result['plain_text'], $result['token']->token);
    }

    public function test_find_by_plain_text(): void
    {
        $user = User::factory()->create();
        $result = ApiToken::createToken($user, 'test-token');

        $found = ApiToken::findByPlainText($result['plain_text']);
        $this->assertNotNull($found);
        $this->assertEquals($result['token']->id, $found->id);
    }

    public function test_find_by_plain_text_returns_null_for_invalid(): void
    {
        $this->assertNull(ApiToken::findByPlainText('invalid-token'));
    }

    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $token = ApiToken::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $token->user->id);
    }

    public function test_token_is_hidden(): void
    {
        $token = ApiToken::factory()->create();
        $array = $token->toArray();

        $this->assertArrayNotHasKey('token', $array);
    }
}
