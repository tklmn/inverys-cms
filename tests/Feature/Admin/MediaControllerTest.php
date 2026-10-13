<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    public function test_admin_can_view_media_index(): void
    {
        $admin = $this->createAdmin();
        Media::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('backend.media.index'))
            ->assertOk();
    }

    public function test_admin_can_upload_media(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

        $this->actingAs($admin)
            ->post(route('backend.media.store'), [
                'file' => $file,
                'alt_text' => 'A test photo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', ['alt_text' => 'A test photo']);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->create('malware.exe', 100);

        $this->actingAs($admin)
            ->post(route('backend.media.store'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $admin = $this->createAdmin();
        // Create a file larger than max_upload_size (10MB = 10240 KB)
        $file = UploadedFile::fake()->create('huge.jpg', 11000, 'image/jpeg');

        $this->actingAs($admin)
            ->post(route('backend.media.store'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_admin_can_edit_media(): void
    {
        $admin = $this->createAdmin();
        $media = Media::factory()->create();

        $this->actingAs($admin)
            ->get(route('backend.media.edit', $media))
            ->assertOk();
    }

    public function test_admin_can_update_media(): void
    {
        $admin = $this->createAdmin();
        $media = Media::factory()->create();

        $this->actingAs($admin)
            ->put(route('backend.media.update', $media), [
                'name' => 'Updated Name',
                'alt_text' => 'Updated alt',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('media', ['id' => $media->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_media(): void
    {
        $admin = $this->createAdmin();
        $media = Media::factory()->create();

        $this->actingAs($admin)
            ->delete(route('backend.media.destroy', $media))
            ->assertRedirect();

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_soft_delete_keeps_file_so_it_can_be_restored(): void
    {
        $admin = $this->createAdmin();
        Storage::disk('public')->put('media/2026/01/keep.jpg', 'binary');
        $media = Media::factory()->create(['path' => 'media/2026/01/keep.jpg']);

        $this->actingAs($admin)
            ->delete(route('backend.media.destroy', $media))
            ->assertRedirect();

        // File must survive a soft delete so a trash restore is not left dangling.
        Storage::disk('public')->assertExists('media/2026/01/keep.jpg');
    }

    public function test_force_delete_removes_the_physical_file(): void
    {
        Storage::disk('public')->put('media/2026/01/gone.jpg', 'binary');
        $media = Media::factory()->create(['path' => 'media/2026/01/gone.jpg']);

        $media->forceDelete();

        Storage::disk('public')->assertMissing('media/2026/01/gone.jpg');
    }

    public function test_editor_can_view_media(): void
    {
        $editor = $this->createEditor();

        $this->actingAs($editor)
            ->get(route('backend.media.index'))
            ->assertOk();
    }

    public function test_media_search_filters_results(): void
    {
        $admin = $this->createAdmin();
        Media::factory()->create(['name' => 'company-logo']);
        Media::factory()->create(['name' => 'team-photo']);

        $this->actingAs($admin)
            ->get(route('backend.media.index', ['search' => 'logo']))
            ->assertOk()
            ->assertSee('company-logo');
    }
}
