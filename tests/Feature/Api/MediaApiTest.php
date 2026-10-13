<?php

namespace Tests\Feature\Api;

use App\Models\Media;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');

        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);
        $this->token = $result['plain_text'];
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_list_media(): void
    {
        Media::factory()->count(3)->create();

        $this->getJson('/api/v1/media', $this->authHeaders())
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_per_page_capped(): void
    {
        Media::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/media?per_page=500', $this->authHeaders());
        $response->assertOk();
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    public function test_upload_media(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $this->postJson('/api/v1/media', [
            'file' => $file,
            'alt_text' => 'Test image',
        ], $this->authHeaders())
            ->assertCreated();

        $this->assertDatabaseHas('media', ['alt_text' => 'Test image']);
    }

    public function test_upload_rejects_disallowed_type(): void
    {
        $file = UploadedFile::fake()->create('hack.php', 100);

        $this->postJson('/api/v1/media', [
            'file' => $file,
        ], $this->authHeaders())
            ->assertUnprocessable();
    }

    public function test_show_media(): void
    {
        $media = Media::factory()->create();

        $this->getJson("/api/v1/media/{$media->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonFragment(['name' => $media->name]);
    }

    public function test_delete_media(): void
    {
        $media = Media::factory()->create();

        $this->deleteJson("/api/v1/media/{$media->id}", [], $this->authHeaders())
            ->assertOk();
    }

    public function test_delete_keeps_files_so_the_media_stays_restorable(): void
    {
        // As in the backend: trash only. Files are only deleted by forceDelete,
        // and that requires manage-trash — not just delete-media via the API.
        Storage::disk('public')->put('media/2026/10/photo.jpg', 'original');
        Storage::disk('public')->put('media/2026/10/thumb_photo.jpg', 'thumb');
        $media = Media::factory()->create([
            'path' => 'media/2026/10/photo.jpg',
            'thumbnail_path' => 'media/2026/10/thumb_photo.jpg',
        ]);

        $this->deleteJson("/api/v1/media/{$media->id}", [], $this->authHeaders())
            ->assertOk();

        $this->assertSoftDeleted($media);
        Storage::disk('public')->assertExists('media/2026/10/photo.jpg');
        Storage::disk('public')->assertExists('media/2026/10/thumb_photo.jpg');
    }
}
