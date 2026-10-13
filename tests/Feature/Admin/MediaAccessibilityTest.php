<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Support\ScriptTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alt texts in the media library: no file names as alt text, a "decorative"
 * flag for images that need none, and a filter for images still missing one.
 */
class MediaAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    public function test_upload_without_alt_text_does_not_use_the_file_name(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), ['file' => UploadedFile::fake()->image('IMG_2041.jpg', 64, 64)])
            ->assertRedirect();

        $media = Media::firstOrFail();
        $this->assertSame('IMG_2041', $media->name);
        $this->assertNull($media->alt_text);
        $this->assertFalse($media->is_decorative);
        $this->assertTrue($media->needsAltText());
    }

    public function test_ajax_upload_keeps_the_alt_text_empty_and_reports_the_flag(): void
    {
        $this->actingAs($this->createAdmin())
            ->postJson(route('backend.media.ajax-upload'), ['file' => UploadedFile::fake()->image('hero.png', 64, 64)])
            ->assertOk()
            ->assertJsonPath('alt_text', null)
            ->assertJsonPath('is_decorative', false);
    }

    public function test_upload_can_mark_an_image_as_decorative(): void
    {
        $this->actingAs($this->createAdmin())
            ->post(route('backend.media.store'), [
                'file' => UploadedFile::fake()->image('ornament.png', 64, 64),
                'is_decorative' => '1',
            ])
            ->assertRedirect();

        $media = Media::firstOrFail();
        $this->assertTrue($media->is_decorative);
        $this->assertFalse($media->needsAltText());
        $this->assertSame('', $media->altAttribute());
    }

    public function test_update_saves_the_decorative_flag(): void
    {
        $media = Media::factory()->image()->create(['alt_text' => 'A red bicycle', 'is_decorative' => false]);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.media.update', $media), [
                'name' => $media->name,
                'alt_text' => 'A red bicycle',
                'is_decorative' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($media->fresh()->is_decorative);
        // Decorative wins: the alt attribute stays empty even with a text
        $this->assertSame('', $media->fresh()->altAttribute());

        $this->actingAs($this->createAdmin())
            ->put(route('backend.media.update', $media), ['name' => $media->name, 'alt_text' => 'A red bicycle', 'is_decorative' => '0'])
            ->assertRedirect();

        $this->assertFalse($media->fresh()->is_decorative);
        $this->assertSame('A red bicycle', $media->fresh()->altAttribute());
    }

    public function test_forms_offer_the_decorative_switch_with_help(): void
    {
        $admin = $this->createAdmin();
        $media = Media::factory()->image()->create();

        $this->actingAs($admin)->get(route('backend.media.create'))
            ->assertOk()
            ->assertSee('name="is_decorative"', false)
            ->assertSee(__('translation.media.is_decorative_help'));

        $this->actingAs($admin)->get(route('backend.media.edit', $media))
            ->assertOk()
            ->assertSee('name="is_decorative"', false);
    }

    public function test_index_marks_and_filters_images_without_alt_text(): void
    {
        $missing = Media::factory()->image()->create(['name' => 'qqmissingqq', 'alt_text' => null, 'is_decorative' => false]);
        Media::factory()->image()->create(['name' => 'qqdescribedqq', 'alt_text' => 'Team photo']);
        Media::factory()->image()->create(['name' => 'qqornamentqq', 'alt_text' => '', 'is_decorative' => true]);
        Media::factory()->pdf()->create(['name' => 'qqbrochureqq', 'alt_text' => null]);

        $this->assertSame([$missing->id], Media::missingAltText()->pluck('id')->all());

        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('backend.media.index'))
            ->assertOk()
            ->assertSee(__('translation.media.alt_missing'))
            ->assertSee(__('translation.media.alt_filter_missing', ['count' => 1]));

        $this->actingAs($admin)->get(route('backend.media.index', ['alt' => 'missing']))
            ->assertOk()
            ->assertSee('qqmissingqq')
            ->assertDontSee('qqdescribedqq')
            ->assertDontSee('qqornamentqq')
            ->assertDontSee('qqbrochureqq');
    }

    public function test_picker_json_includes_the_decorative_flag(): void
    {
        Media::factory()->image()->create(['alt_text' => null, 'is_decorative' => true]);

        $this->actingAs($this->createAdmin())
            ->getJson(route('backend.media.api.list'))
            ->assertOk()
            ->assertJsonPath('data.0.is_decorative', true)
            ->assertJsonPath('data.0.alt', null);
    }

    public function test_find_by_path_accepts_the_stored_forms(): void
    {
        $media = Media::factory()->image()->create(['path' => 'media/2026/10/header.jpg', 'alt_text' => 'Harbour at dawn']);

        $this->assertTrue($media->is(Media::findByPath('media/2026/10/header.jpg')));
        $this->assertTrue($media->is(Media::findByPath('storage/media/2026/10/header.jpg')));
        $this->assertTrue($media->is(Media::findByPath(asset('storage/media/2026/10/header.jpg'))));
        $this->assertNull(Media::findByPath('https://example.com/elsewhere.jpg'));
        $this->assertNull(Media::findByPath(null));
    }

    public function test_editor_receives_the_installed_content_languages(): void
    {
        $languages = ScriptTexts::contentLanguages();

        $this->assertContains(['title' => 'English', 'code' => 'en'], $languages);
        $this->assertArrayHasKey('content_langs', ScriptTexts::backend());
    }
}
