<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Page;
use App\Models\Revision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_trash_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.trash.index'))
            ->assertOk();
    }

    public function test_admin_can_restore_soft_deleted_page(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create(['title' => 'Trashed Page']);
        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $this->actingAs($admin)
            ->post(route('backend.trash.restore', ['type' => 'page', 'id' => $page->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_force_delete_soft_deleted_page(): void
    {
        $admin = $this->createAdmin();
        $page = Page::factory()->create(['title' => 'Permanently Delete Me']);
        $page->delete();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);

        $this->actingAs($admin)
            ->delete(route('backend.trash.forceDelete', ['type' => 'page', 'id' => $page->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_media_actions_return_to_the_media_tab(): void
    {
        $admin = $this->createAdmin();
        $media = Media::create(['name' => 'x', 'file_name' => 'x.pdf', 'path' => 'media/x.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
        $media->delete();

        $this->actingAs($admin)
            ->post(route('backend.trash.restore', ['type' => 'media', 'id' => $media->id]))
            ->assertRedirect(route('backend.trash.index', ['tab' => 'media']));

        $media->delete();

        $this->delete(route('backend.trash.forceDelete', ['type' => 'media', 'id' => $media->id]))
            ->assertRedirect(route('backend.trash.index', ['tab' => 'media']));
    }

    public function test_force_delete_removes_page_revisions(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);
        $page = Page::factory()->create(['title' => 'Old']);
        $page->update(['title' => 'New']);
        $this->assertSame(1, $page->revisions()->count());

        // The trash keeps the revisions, permanent deletion removes them
        $page->delete();
        $this->assertSame(1, Revision::count());

        $this->delete(route('backend.trash.forceDelete', ['type' => 'page', 'id' => $page->id]))->assertRedirect();

        $this->assertSame(0, Revision::count());
    }

    public function test_user_without_manage_trash_permission_gets_403(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->get(route('backend.trash.index'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('backend.trash.index'))
            ->assertRedirect();
    }
}
