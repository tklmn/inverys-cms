<?php

namespace Tests\Feature\Admin;

use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_api_tokens_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.api-tokens.index'))
            ->assertOk();
    }

    public function test_admin_can_create_api_token(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.api-tokens.store'), [
                'name' => 'Test Token',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $admin->id,
            'name' => 'Test Token',
        ]);
    }

    public function test_admin_can_delete_api_token(): void
    {
        $admin = $this->createAdmin();
        $token = ApiToken::factory()->create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->delete(route('backend.api-tokens.destroy', $token))
            ->assertRedirect();

        $this->assertDatabaseMissing('api_tokens', ['id' => $token->id]);
    }

    public function test_user_without_api_access_permission_gets_403(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.api-tokens.index'))
            ->assertForbidden();
    }
}
