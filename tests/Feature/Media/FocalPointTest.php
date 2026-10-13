<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Services\BlockRenderer;
use App\Support\ResponsiveImage;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Focal point of an image: stored on the media item (percent from the
 * left / top, null = center), set in the media library, exposed in the
 * JSON of the pickers and the API, and applied as object-position wherever
 * the frontend crops an image.
 */
class FocalPointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    private function photo(array $attributes = []): Media
    {
        return Media::factory()->image()->create(array_merge([
            'path' => 'media/2026/10/photo.jpg',
            'width' => 2000,
            'height' => 1000,
            'alt_text' => 'Mountains',
            'variants' => [
                ['width' => 480, 'height' => 240, 'path' => 'media/2026/10/photo-480w.webp', 'mime' => 'image/webp'],
            ],
        ], $attributes));
    }

    private function update(Media $media, array $data)
    {
        return $this->actingAs($this->createAdmin())
            ->put(route('backend.media.update', $media), array_merge(['name' => $media->name, 'alt_text' => $media->alt_text], $data));
    }

    // ---------------------------------------------------------------
    // Model
    // ---------------------------------------------------------------

    public function test_columns_exist_and_default_to_the_center(): void
    {
        $this->assertTrue(Schema::hasColumns('media', ['focal_x', 'focal_y']));

        $media = $this->photo();
        $this->assertNull($media->fresh()->focal_x);
        $this->assertNull($media->fresh()->focal_y);
        $this->assertFalse($media->hasFocalPoint());
        $this->assertNull($media->focalPosition());
    }

    public function test_values_are_cast_to_floats_and_formatted_as_css(): void
    {
        $media = $this->photo(['focal_x' => '30.50', 'focal_y' => 70]);
        $media = $media->fresh();

        $this->assertSame(30.5, $media->focal_x);
        $this->assertSame(70.0, $media->focal_y);
        $this->assertSame('30.5% 70%', $media->focalPosition());

        $media->update(['focal_x' => 0, 'focal_y' => 100]);
        $this->assertSame('0% 100%', $media->fresh()->focalPosition());

        // Exactly the center is the default: nothing to output
        $media->update(['focal_x' => 50, 'focal_y' => 50]);
        $this->assertNull($media->fresh()->focalPosition());
    }

    public function test_focal_point_from_input_fills_a_missing_half_with_the_center(): void
    {
        $this->assertSame(['focal_x' => null, 'focal_y' => null], Media::focalPointFrom(null, ''));
        $this->assertSame(['focal_x' => 20.0, 'focal_y' => 50.0], Media::focalPointFrom('20', null));
        $this->assertSame(['focal_x' => 33.33, 'focal_y' => 100.0], Media::focalPointFrom('33.333', '140'));
    }

    // ---------------------------------------------------------------
    // Media library edit form
    // ---------------------------------------------------------------

    public function test_edit_form_saves_the_focal_point(): void
    {
        $media = $this->photo();

        $this->update($media, ['focal_x' => '25', 'focal_y' => '80.5'])->assertRedirect(route('backend.media.edit', $media));

        $media->refresh();
        $this->assertSame(25.0, $media->focal_x);
        $this->assertSame(80.5, $media->focal_y);

        // Reset to center: both fields empty
        $this->update($media, ['focal_x' => '', 'focal_y' => ''])->assertRedirect();
        $this->assertNull($media->fresh()->focal_x);
        $this->assertNull($media->fresh()->focal_y);
    }

    public function test_saving_without_the_fields_keeps_the_focal_point(): void
    {
        $media = $this->photo(['focal_x' => 10, 'focal_y' => 20]);

        $this->update($media, ['description' => 'Changed'])->assertRedirect();

        $this->assertSame(10.0, $media->fresh()->focal_x);
        $this->assertSame(20.0, $media->fresh()->focal_y);
    }

    public function test_validation_accepts_only_0_to_100(): void
    {
        $media = $this->photo();

        foreach ([['focal_x' => '101'], ['focal_y' => '-1'], ['focal_x' => 'left'], ['focal_y' => '1e9']] as $data) {
            $this->update($media, $data)->assertSessionHasErrors(array_keys($data));
        }

        $this->assertNull($media->fresh()->focal_x);
        $this->assertNull($media->fresh()->focal_y);
    }

    public function test_edit_page_offers_an_accessible_focal_point_control(): void
    {
        $media = $this->photo(['focal_x' => 30, 'focal_y' => 70]);

        $this->actingAs($this->createAdmin())->get(route('backend.media.edit', $media))
            ->assertOk()
            ->assertSee('data-focal-point', false)
            ->assertSee('aria-label="'.__('translation.media.focal_marker').'"', false)
            ->assertSee('style="left: 30%; top: 70%;"', false)
            ->assertSee('name="focal_x"', false)
            ->assertSee('name="focal_y"', false)
            ->assertSee('<label class="ui-label" for="focal_x">', false)
            ->assertSee(__('translation.media.focal_reset'))
            ->assertSee(__('translation.media.focal_position', ['x' => 30, 'y' => 70]));
    }

    public function test_edit_page_of_a_document_has_no_focal_point(): void
    {
        $pdf = Media::factory()->pdf()->create();

        $this->actingAs($this->createAdmin())->get(route('backend.media.edit', $pdf))
            ->assertOk()
            ->assertDontSee('data-focal-point', false);
    }

    // ---------------------------------------------------------------
    // JSON: index, picker, upload, API
    // ---------------------------------------------------------------

    public function test_index_and_picker_json_include_the_focal_point(): void
    {
        $this->photo(['focal_x' => 12.5, 'focal_y' => 40]);
        $admin = $this->createAdmin();

        $this->actingAs($admin)->getJson(route('backend.media.index'))
            ->assertOk()
            ->assertJsonPath('data.0.focal_x', 12.5)
            ->assertJsonPath('data.0.focal_y', 40);

        $this->actingAs($admin)->getJson(route('backend.media.api.list'))
            ->assertOk()
            ->assertJsonPath('data.0.focal_x', 12.5)
            ->assertJsonPath('data.0.focal_y', 40);
    }

    public function test_ajax_upload_reports_the_center(): void
    {
        $this->actingAs($this->createAdmin())
            ->postJson(route('backend.media.ajax-upload'), ['file' => UploadedFile::fake()->image('photo.jpg', 64, 64)])
            ->assertOk()
            ->assertJsonPath('focal_x', null)
            ->assertJsonPath('focal_y', null);
    }

    public function test_api_exposes_and_accepts_the_focal_point(): void
    {
        $media = $this->photo(['focal_x' => 75, 'focal_y' => 25]);
        $token = ApiTokenFactory::createWithPlainText($this->createAdmin())['plain_text'];
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/media/'.$media->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.focal_x', 75)
            ->assertJsonPath('data.focal_y', 25);

        $this->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('api.jpg', 64, 64), 'focal_x' => 10, 'focal_y' => 90], $headers)
            ->assertCreated()
            ->assertJsonPath('data.focal_x', 10)
            ->assertJsonPath('data.focal_y', 90);

        $this->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('bad.jpg', 64, 64), 'focal_x' => 150], $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('focal_x');
    }

    // ---------------------------------------------------------------
    // Frontend: object-position on cropped images
    // ---------------------------------------------------------------

    public function test_helper_emits_object_position_for_a_focal_point(): void
    {
        $this->photo(['focal_x' => 20, 'focal_y' => 85.25]);

        $attributes = ResponsiveImage::attributes('storage/media/2026/10/photo.jpg');
        $this->assertSame('object-position: 20% 85.25%;', $attributes['style']);

        // Opt-out for object-fit: contain
        $this->assertArrayNotHasKey('style', app(ResponsiveImage::class)->build('storage/media/2026/10/photo.jpg', ['focal' => false]));

        $this->assertSame('background-position: 20% 85.25%;', app(ResponsiveImage::class)->backgroundPosition('storage/media/2026/10/photo.jpg'));
    }

    public function test_center_and_external_images_emit_no_position(): void
    {
        $this->photo();

        $this->assertArrayNotHasKey('style', ResponsiveImage::attributes('storage/media/2026/10/photo.jpg'));
        $this->assertArrayNotHasKey('style', ResponsiveImage::attributes('https://example.org/cat.jpg'));
        $this->assertSame('', app(ResponsiveImage::class)->backgroundPosition('storage/media/2026/10/photo.jpg'));
    }

    public function test_component_merges_the_position_with_a_given_style(): void
    {
        $this->photo(['focal_x' => 10, 'focal_y' => 30]);

        $html = Blade::render('<x-responsive-image :src="$src" alt="" class="object-cover" style="border:1px solid red" />', ['src' => 'storage/media/2026/10/photo.jpg']);
        $this->assertStringContainsString('style="object-position: 10% 30%; border:1px solid red;"', $html);

        $plain = Blade::render('<x-responsive-image :src="$src" :focal="false" alt="" />', ['src' => 'storage/media/2026/10/photo.jpg']);
        $this->assertStringNotContainsString('object-position', $plain);
    }

    public function test_cropped_blocks_use_the_focal_point_and_contained_images_do_not(): void
    {
        $this->photo(['focal_x' => 15, 'focal_y' => 60]);
        $src = 'storage/media/2026/10/photo.jpg';

        $html = app(BlockRenderer::class)->render([
            ['id' => 'i', 'type' => 'image', 'data' => ['src' => $src, 'alt' => 'Image block'], 'settings' => ['ratio' => '1-1']],
            ['id' => 'c', 'type' => 'card', 'data' => ['image' => $src, 'image_alt' => 'Cover card', 'title' => 'Cover'], 'settings' => []],
            ['id' => 'k', 'type' => 'card', 'data' => ['image' => $src, 'image_alt' => 'Contain card', 'title' => 'Contain'], 'settings' => ['image_style' => 'contain']],
            ['id' => 's', 'type' => 'section', 'data' => [], 'settings' => ['bg_image' => $src], 'children' => []],
            ['id' => 'l', 'type' => 'logos', 'data' => ['items' => [['image' => $src, 'alt' => 'Logo']]], 'settings' => []],
        ], prioritizeFirstImage: false);

        $tag = function (string $pattern) use ($html): string {
            $this->assertMatchesRegularExpression($pattern, $html);
            preg_match($pattern, $html, $m);

            return $m[0];
        };

        $this->assertStringContainsString('object-position: 15% 60%;', $tag('/<img[^>]*alt="Image block"[^>]*>/'));
        $this->assertStringContainsString('object-position: 15% 60%;', $tag('/<img[^>]*alt="Cover card"[^>]*>/'));
        $this->assertStringContainsString('object-position: 15% 60%;', $tag('/<img[^>]*class="site-bg-media"[^>]*>/'));
        $this->assertStringNotContainsString('object-position', $tag('/<img[^>]*alt="Contain card"[^>]*>/'));
        $this->assertStringNotContainsString('object-position', $tag('/<img[^>]*alt="Logo"[^>]*>/'));
    }
}
