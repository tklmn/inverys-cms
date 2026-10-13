<?php

namespace Tests\Feature\Api;

use App\Services\ThemeManager;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeApiTest extends TestCase
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

    public function test_list_themes(): void
    {
        // Mock ThemeManager to return predictable data
        $themeManager = $this->mock(ThemeManager::class);
        $themeManager->shouldReceive('all')->once()->andReturn([
            'default' => [
                'slug' => 'default',
                'name' => 'Default Theme',
                'version' => '1.0.0',
                'description' => 'The default theme',
                'author' => 'Inverys',
            ],
            'ember' => [
                'slug' => 'ember',
                'name' => 'Ember',
                'version' => '2.0.0',
                'description' => 'A dark theme',
                'author' => 'Inverys',
            ],
        ]);
        $themeManager->shouldReceive('getActive')->once()->andReturn('default');

        $response = $this->getJson('/api/v1/themes', $this->authHeaders());
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['active' => 'default']);
    }

    public function test_list_themes_returns_active_indicator(): void
    {
        $themeManager = $this->mock(ThemeManager::class);
        $themeManager->shouldReceive('all')->once()->andReturn([
            'default' => [
                'slug' => 'default',
                'name' => 'Default Theme',
                'version' => '1.0.0',
                'description' => 'The default theme',
                'author' => 'Inverys',
            ],
        ]);
        $themeManager->shouldReceive('getActive')->once()->andReturn('default');

        $response = $this->getJson('/api/v1/themes', $this->authHeaders());
        $response->assertOk()
            ->assertJsonPath('active', 'default');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/themes')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createUser();
        $result = ApiTokenFactory::createWithPlainText($user);

        $this->getJson('/api/v1/themes', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }

    public function test_editor_without_manage_themes_gets_403(): void
    {
        $editor = $this->createEditor();
        $result = ApiTokenFactory::createWithPlainText($editor);

        $this->getJson('/api/v1/themes', [
            'Authorization' => 'Bearer '.$result['plain_text'],
        ])->assertForbidden();
    }
}
