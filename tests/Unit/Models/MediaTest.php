<?php

namespace Tests\Unit\Models;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_attribute_for_image(): void
    {
        $media = Media::factory()->create(['mime_type' => 'image/jpeg']);
        $this->assertEquals('image', $media->type);
    }

    public function test_type_attribute_for_pdf(): void
    {
        $media = Media::factory()->create(['mime_type' => 'application/pdf']);
        $this->assertEquals('pdf', $media->type);
    }

    public function test_type_attribute_for_word(): void
    {
        $media = Media::factory()->create(['mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        $this->assertEquals('doc', $media->type);
    }

    public function test_type_attribute_for_excel(): void
    {
        $media = Media::factory()->create(['mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        $this->assertEquals('xls', $media->type);
    }

    public function test_type_attribute_for_video(): void
    {
        $media = Media::factory()->create(['mime_type' => 'video/mp4']);
        $this->assertEquals('video', $media->type);
    }

    public function test_type_attribute_for_audio(): void
    {
        $media = Media::factory()->create(['mime_type' => 'audio/mpeg']);
        $this->assertEquals('audio', $media->type);
    }

    public function test_type_attribute_for_unknown(): void
    {
        $media = Media::factory()->create(['mime_type' => 'application/octet-stream']);
        $this->assertEquals('file', $media->type);
    }

    public function test_formatted_size_bytes(): void
    {
        $media = Media::factory()->create(['size' => 500]);
        $this->assertEquals('500 bytes', $media->formatted_size);
    }

    public function test_formatted_size_kb(): void
    {
        $media = Media::factory()->create(['size' => 2048]);
        $this->assertEquals('2.00 KB', $media->formatted_size);
    }

    public function test_formatted_size_mb(): void
    {
        $media = Media::factory()->create(['size' => 1048576 * 3]);
        $this->assertEquals('3.00 MB', $media->formatted_size);
    }

    public function test_size_is_cast_to_integer(): void
    {
        $media = Media::factory()->create(['size' => '12345']);
        $this->assertIsInt($media->size);
    }

    public function test_url_attribute(): void
    {
        $media = Media::factory()->create(['path' => 'media/2026/03/test.jpg']);
        $this->assertStringContainsString('storage/media/2026/03/test.jpg', $media->url);
    }

    public function test_user_relationship(): void
    {
        $media = Media::factory()->create();
        $this->assertNotNull($media->user);
    }

    public function test_soft_delete(): void
    {
        $media = Media::factory()->create();
        $media->delete();

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    public function test_scope_of_type(): void
    {
        Media::factory()->create(['mime_type' => 'image/jpeg']);
        Media::factory()->create(['mime_type' => 'application/pdf']);
        Media::factory()->create(['mime_type' => 'video/mp4']);

        $this->assertEquals(1, Media::ofType('image')->count());
        $this->assertEquals(1, Media::ofType('document')->count());
        $this->assertEquals(1, Media::ofType('video')->count());
    }

    public function test_scope_search(): void
    {
        Media::factory()->create(['name' => 'logo-design']);
        Media::factory()->create(['name' => 'photo-beach']);

        $this->assertEquals(1, Media::search('logo')->count());
        $this->assertEquals(1, Media::search('beach')->count());
        $this->assertEquals(0, Media::search('nonexistent')->count());
    }

    public function test_scope_search_escapes_wildcards(): void
    {
        // The search scope escapes LIKE wildcards with a portable ESCAPE clause,
        // so '_' and '%' are matched literally on every driver — SQLite included.
        Media::factory()->create(['name' => 'file_with_underscore']);
        Media::factory()->create(['name' => 'fileXwithXchars']);

        // '_' must be treated literally, not as a single-character wildcard.
        $this->assertEquals(1, Media::search('file_with')->count());
    }

    public function test_scope_search_escapes_percent_wildcard(): void
    {
        // '%' must be treated literally, not as a multi-character wildcard.
        Media::factory()->create(['name' => 'report%2026']);
        Media::factory()->create(['name' => 'report-final-2026']);

        $this->assertEquals(1, Media::search('report%2026')->count());
    }
}
