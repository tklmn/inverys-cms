<?php

namespace Tests\Feature\Api;

use App\Models\BlockType;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();

        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);
        $this->token = $result['plain_text'];
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_list_block_types(): void
    {
        BlockType::factory()->count(4)->create();

        $response = $this->getJson('/api/v1/blocks/types', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'category', 'icon', 'is_core'],
                ],
            ]);
    }

    public function test_list_block_types_includes_core_and_plugin_types(): void
    {
        BlockType::factory()->create([
            'name' => 'Text Block',
            'is_core' => true,
            'plugin_slug' => null,
        ]);
        BlockType::factory()->plugin('gallery')->create([
            'name' => 'Gallery Block',
        ]);

        $response = $this->getJson('/api/v1/blocks/types', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Text Block', $names);
        $this->assertContains('Gallery Block', $names);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/blocks/types')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createUser();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->getJson('/api/v1/blocks/types', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }
}
