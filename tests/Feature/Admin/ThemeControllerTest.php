<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_themes_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.themes.index'))
            ->assertOk();
    }

    public function test_editor_without_manage_themes_gets_403(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.themes.index'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('backend.themes.index'))
            ->assertRedirect();
    }
}
