<?php

namespace Tests\Feature\Admin;

use App\Models\PageCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PageCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_page_categories_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.page-categories.index'))
            ->assertOk();
    }

    public function test_admin_can_view_create_page_category_form(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.page-categories.create'))
            ->assertOk();
    }

    public function test_admin_can_store_page_category(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.page-categories.store'), [
                'name' => 'News',
                'description' => 'News articles',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('page_categories', [
            'name' => 'News',
            'slug' => 'news',
            'description' => 'News articles',
        ]);
    }

    public function test_admin_can_view_edit_page_category_form(): void
    {
        $admin = $this->createAdmin();
        $category = PageCategory::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.page-categories.edit', $category))
            ->assertOk();
    }

    public function test_admin_can_update_page_category(): void
    {
        $admin = $this->createAdmin();
        $category = PageCategory::factory()->create();

        $this->actingAs($admin)
            ->put(route('backend.page-categories.update', $category), [
                'name' => 'Updated Category',
                'description' => 'Updated description',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('page_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'slug' => 'updated-category',
        ]);
    }

    public function test_admin_can_delete_page_category(): void
    {
        $admin = $this->createAdmin();
        $category = PageCategory::factory()->create();

        $this->actingAs($admin)
            ->delete(route('backend.page-categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('page_categories', ['id' => $category->id]);
    }

    public function test_store_requires_name_validation(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('backend.page-categories.store'), [
                'description' => 'Missing name',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_user_without_delete_permission_cannot_delete_page_categories(): void
    {
        $user = $this->createUser();
        $category = PageCategory::factory()->create();

        $this->actingAs($user)
            ->delete(route('backend.page-categories.destroy', $category))
            ->assertForbidden();
    }

    /**
     * The controller has no show() action. The resource route must exclude it,
     * otherwise a GET on the member URL 500s with "Method … does not exist".
     */
    public function test_show_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('backend.page-categories.show'));

        $admin = $this->createAdmin();
        $category = PageCategory::factory()->create();

        $response = $this->actingAs($admin)->get("/backend/page-categories/{$category->id}");

        $this->assertNotSame(500, $response->getStatusCode());
    }
}
