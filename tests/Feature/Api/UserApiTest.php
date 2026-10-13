<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        // Fixed, non-random admin so its faker name/email can never collide with
        // the search terms used below ("Alice", "findme") — otherwise the search
        // assertions are flaky.
        $this->admin = $this->createAdmin([
            'name' => 'Api Admin',
            'email' => 'api-admin@example.test',
        ]);
        $result = ApiTokenFactory::createWithPlainText($this->admin);
        $this->token = $result['plain_text'];
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users', $this->authHeaders());
        $response->assertOk();

        // 3 factory users + 1 admin from setUp
        $response->assertJsonCount(4, 'data');
    }

    public function test_search_filters_users_by_name(): void
    {
        User::factory()->create(['name' => 'Alice Wonderland']);
        User::factory()->create(['name' => 'Bob Builder']);

        $response = $this->getJson('/api/v1/users?search=Alice', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Alice Wonderland', $response->json('data.0.name'));
    }

    public function test_search_filters_users_by_email(): void
    {
        User::factory()->create(['email' => 'findme@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->getJson('/api/v1/users?search=findme', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals('findme@example.com', $response->json('data.0.email'));
    }

    public function test_per_page_is_capped_at_100(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/users?per_page=9999', $this->authHeaders());
        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    public function test_show_user_with_roles(): void
    {
        $response = $this->getJson("/api/v1/users/{$this->admin->id}", $this->authHeaders());
        $response->assertOk()
            ->assertJsonFragment(['email' => $this->admin->email])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                ],
            ]);

        $this->assertContains('admin', $response->json('data.roles'));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/users')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createUser();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->getJson('/api/v1/users', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }
}
