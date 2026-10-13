<?php

namespace Tests\Feature\Api;

use App\Models\Plugin;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginApiTest extends TestCase
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

    public function test_list_plugins(): void
    {
        Plugin::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/plugins', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'slug', 'name', 'version', 'is_enabled'],
                ],
            ]);
    }

    public function test_list_plugins_includes_enabled_and_disabled(): void
    {
        Plugin::factory()->enabled()->create(['name' => 'Enabled Plugin']);
        Plugin::factory()->create(['name' => 'Disabled Plugin']);

        $response = $this->getJson('/api/v1/plugins', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Enabled Plugin', $names);
        $this->assertContains('Disabled Plugin', $names);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/plugins')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createUser();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->getJson('/api/v1/plugins', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }

    public function test_editor_without_manage_plugins_gets_403(): void
    {
        $editor = $this->createEditor();
        $result = ApiTokenFactory::createWithPlainText($editor);

        $this->getJson('/api/v1/plugins', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }
}
