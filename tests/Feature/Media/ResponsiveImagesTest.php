<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Page;
use App\Services\BlockRenderer;
use App\Services\MediaImageProcessor;
use App\Support\EditorCanvas;
use App\Support\ResponsiveImage;
use App\Support\Seo\Urls;
use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Responsive images: size and scaled copies on upload, the backfill
 * command, the <img> helper and the "first image of the page" priority.
 */
class ResponsiveImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        Storage::fake('public');
    }

    private function upload(UploadedFile $file): Media
    {
        $this->actingAs($this->createAdmin())
            ->postJson(route('backend.media.ajax-upload'), ['file' => $file, 'alt_text' => 'A photo'])
            ->assertOk();

        return Media::latest('id')->firstOrFail();
    }

    /** A library entry with a stored image file (as uploaded before this feature existed) */
    private function legacyImage(int $width, int $height, string $path = 'media/2026/01/old.jpg'): Media
    {
        $file = UploadedFile::fake()->image('old.jpg', $width, $height);
        Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

        return Media::factory()->create([
            'path' => $path,
            'file_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'alt_text' => 'Old photo',
            'description' => 'Keep me',
        ]);
    }

    private function mediaWithVariants(string $path = 'media/2026/10/photo.jpg'): Media
    {
        return Media::factory()->create([
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'width' => 2000,
            'height' => 1000,
            'alt_text' => 'Mountains',
            'variants' => [
                ['width' => 480, 'height' => 240, 'path' => 'media/2026/10/photo-480w.webp', 'mime' => 'image/webp'],
                ['width' => 1280, 'height' => 640, 'path' => 'media/2026/10/photo-1280w.webp', 'mime' => 'image/webp'],
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // Upload: size and scaled copies
    // ---------------------------------------------------------------

    public function test_upload_stores_the_image_size(): void
    {
        $media = $this->upload(UploadedFile::fake()->image('photo.jpg', 640, 480));

        $this->assertSame(640, $media->width);
        $this->assertSame(480, $media->height);
    }

    public function test_upload_creates_scaled_copies_without_upscaling(): void
    {
        $media = $this->upload(UploadedFile::fake()->image('photo.jpg', 1500, 1000));

        $widths = array_column($media->variants, 'width');
        $this->assertSame([480, 768, 1280], $widths, 'Only copies smaller than the original');

        $format = MediaImageProcessor::writesWebp() ? 'webp' : 'jpg';
        foreach ($media->variants as $variant) {
            Storage::disk('public')->assertExists($variant['path']);
            $this->assertStringEndsWith('-'.$variant['width'].'w.'.$format, $variant['path']);
            $this->assertStringStartsWith(preg_replace('/\.jpg$/', '', $media->path), $variant['path']);
            $this->assertSame((int) round(1000 * $variant['width'] / 1500), $variant['height']);
        }

        // The backend thumbnail is still there
        Storage::disk('public')->assertExists($media->thumbnail_path);
    }

    public function test_variants_keep_the_original_format_when_webp_is_off(): void
    {
        config(['cms.media.webp_variants' => false]);

        $media = $this->upload(UploadedFile::fake()->image('photo.png', 900, 600));

        $this->assertSame([480, 768], array_column($media->variants, 'width'));
        $this->assertSame(['image/png', 'image/png'], array_column($media->variants, 'mime'));
        $this->assertStringEndsWith('-480w.png', $media->variants[0]['path']);
    }

    public function test_small_images_and_gifs_get_no_copies(): void
    {
        $small = $this->upload(UploadedFile::fake()->image('small.jpg', 400, 300));
        $this->assertNull($small->variants);
        $this->assertSame(400, $small->width);

        $gif = $this->upload(UploadedFile::fake()->image('anim.gif', 1600, 900));
        $this->assertNull($gif->variants, 'GIFs stay untouched (animation)');
        $this->assertSame(1600, $gif->width);
        $this->assertSame(900, $gif->height);
    }

    public function test_api_upload_stores_size_and_copies(): void
    {
        $token = ApiTokenFactory::createWithPlainText($this->createAdmin())['plain_text'];

        $this->postJson('/api/v1/media', ['file' => UploadedFile::fake()->image('api.jpg', 1000, 500)], ['Authorization' => 'Bearer '.$token])
            ->assertCreated()
            ->assertJsonPath('data.width', 1000)
            ->assertJsonPath('data.height', 500);

        $media = Media::latest('id')->firstOrFail();
        $this->assertSame([480, 768], array_column($media->variants, 'width'));
        $this->assertNotNull($media->thumbnail_path);
    }

    public function test_permanent_deletion_removes_the_copies(): void
    {
        $media = $this->upload(UploadedFile::fake()->image('photo.jpg', 1000, 800));
        $paths = array_column($media->variants, 'path');
        $this->assertNotEmpty($paths);

        // Trash: the files stay, the item can be restored
        $media->delete();
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $media->forceDelete();
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        Storage::disk('public')->assertMissing($media->path);
    }

    // ---------------------------------------------------------------
    // Backfill command
    // ---------------------------------------------------------------

    public function test_backfill_command_adds_size_and_copies_and_is_idempotent(): void
    {
        $media = $this->legacyImage(1300, 650);
        $updatedAt = $media->updated_at->toDateTimeString();
        $this->travel(5)->minutes();

        $this->artisan('media:variants')->assertSuccessful();

        $media->refresh();
        $this->assertSame(1300, $media->width);
        $this->assertSame(650, $media->height);
        $this->assertSame([480, 768, 1280], array_column($media->variants, 'width'));
        // Nothing else changes
        $this->assertSame('Old photo', $media->alt_text);
        $this->assertSame('Keep me', $media->description);
        $this->assertSame($updatedAt, $media->updated_at->toDateTimeString());

        $first = $media->variants;
        $this->artisan('media:variants')->assertSuccessful();
        $this->assertSame($first, $media->refresh()->variants, 'A second run changes nothing');

        // A missing copy is regenerated
        Storage::disk('public')->delete($first[0]['path']);
        $this->artisan('media:variants')->assertSuccessful();
        Storage::disk('public')->assertExists($media->refresh()->variants[0]['path']);
    }

    public function test_backfill_skips_svgs_and_missing_files(): void
    {
        $svg = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/demo/logo.svg']);
        $missing = Media::factory()->create(['mime_type' => 'image/jpeg', 'path' => 'media/2026/01/gone.jpg']);

        $this->artisan('media:variants')->assertSuccessful();

        $this->assertNull($svg->refresh()->width);
        $this->assertNull($svg->variants);
        $this->assertNull($missing->refresh()->variants);
    }

    // ---------------------------------------------------------------
    // The <img> helper
    // ---------------------------------------------------------------

    public function test_helper_outputs_srcset_sizes_and_dimensions(): void
    {
        $this->mediaWithVariants();

        $attributes = ResponsiveImage::attributes('storage/media/2026/10/photo.jpg');

        $this->assertSame(asset('storage/media/2026/10/photo.jpg'), $attributes['src']);
        $this->assertSame(
            asset('storage/media/2026/10/photo-480w.webp').' 480w, '.asset('storage/media/2026/10/photo-1280w.webp').' 1280w, '.asset('storage/media/2026/10/photo.jpg').' 2000w',
            $attributes['srcset'],
        );
        $this->assertSame('auto, '.ResponsiveImage::DEFAULT_SIZES, $attributes['sizes']);
        $this->assertSame(2000, $attributes['width']);
        $this->assertSame(1000, $attributes['height']);
        $this->assertSame('lazy', $attributes['loading']);
        $this->assertSame('async', $attributes['decoding']);
        $this->assertArrayNotHasKey('fetchpriority', $attributes);
    }

    public function test_helper_priority_loads_eagerly_with_high_priority(): void
    {
        $this->mediaWithVariants();

        $attributes = ResponsiveImage::attributes(asset('storage/media/2026/10/photo.jpg'), ['priority' => true, 'sizes' => '100vw']);

        $this->assertSame('eager', $attributes['loading']);
        $this->assertSame('high', $attributes['fetchpriority']);
        $this->assertSame('100vw', $attributes['sizes'], 'No sizes="auto" for eager images');
    }

    public function test_helper_falls_back_to_a_plain_image(): void
    {
        $external = ResponsiveImage::attributes('https://example.org/cat.jpg');
        $this->assertSame(['src' => 'https://example.org/cat.jpg', 'loading' => 'lazy', 'decoding' => 'async'], $external);

        // SVG from the library: no copies, no pixel size
        Media::factory()->create(['path' => 'media/demo/logo.svg', 'mime_type' => 'image/svg+xml']);
        $svg = ResponsiveImage::attributes('storage/media/demo/logo.svg');
        $this->assertSame(asset('storage/media/demo/logo.svg'), $svg['src']);
        $this->assertArrayNotHasKey('srcset', $svg);
        $this->assertArrayNotHasKey('width', $svg);

        // Unknown file and theme assets: no query, plain image
        $this->assertArrayNotHasKey('srcset', ResponsiveImage::attributes('storage/media/2026/10/unknown.jpg'));
        $this->assertSame(asset('images/hero.png'), ResponsiveImage::attributes('images/hero.png')['src']);
    }

    public function test_component_passes_other_attributes_through(): void
    {
        $this->mediaWithVariants();

        $html = Blade::render('<x-responsive-image :src="$src" alt="" class="site-bg-media" role="presentation" data-pb-field="image" sizes="100vw" />', ['src' => 'storage/media/2026/10/photo.jpg']);

        $this->assertStringContainsString('alt=""', $html);
        $this->assertStringContainsString('class="site-bg-media"', $html);
        $this->assertStringContainsString('role="presentation"', $html);
        $this->assertStringContainsString('data-pb-field="image"', $html);
        $this->assertStringContainsString('width="2000" height="1000"', $html);
        $this->assertStringContainsString('sizes="auto, 100vw"', $html);
        $this->assertStringContainsString('photo-480w.webp 480w', $html);
    }

    public function test_og_image_size_comes_from_the_library(): void
    {
        $this->mediaWithVariants('media/2026/10/share.jpg');

        // The file itself does not exist: the stored size is used
        $this->assertSame([2000, 1000], Urls::imageSize('storage/media/2026/10/share.jpg'));
        $this->assertSame([2000, 1000], Urls::imageSize(asset('storage/media/2026/10/share.jpg')));
    }

    // ---------------------------------------------------------------
    // Page: the first image block gets the priority
    // ---------------------------------------------------------------

    private function imageBlock(string $id, string $src = 'storage/media/2026/10/photo.jpg'): array
    {
        return ['id' => $id, 'type' => 'image', 'data' => ['src' => $src, 'alt' => 'Alt '.$id], 'settings' => []];
    }

    public function test_first_image_block_of_the_page_gets_high_priority(): void
    {
        $this->mediaWithVariants();

        $html = app(BlockRenderer::class)->render([
            ['id' => 't', 'type' => 'text', 'data' => ['content' => '<p>Intro</p>'], 'settings' => []],
            $this->imageBlock('a'),
            $this->imageBlock('b'),
        ]);

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertMatchesRegularExpression('/<img[^>]*alt="Alt a"[^>]*>/', $html);
        preg_match('/<img[^>]*alt="Alt a"[^>]*>/', $html, $first);
        preg_match('/<img[^>]*alt="Alt b"[^>]*>/', $html, $second);
        $this->assertStringContainsString('fetchpriority="high"', $first[0]);
        $this->assertStringContainsString('loading="eager"', $first[0]);
        $this->assertStringContainsString('loading="lazy"', $second[0]);
        $this->assertStringNotContainsString('fetchpriority', $second[0]);
    }

    public function test_container_background_comes_before_the_images_inside(): void
    {
        $html = app(BlockRenderer::class)->render([
            ['id' => 'c', 'type' => 'container', 'data' => [], 'settings' => ['bg_image' => 'storage/media/bg.jpg'], 'children' => [
                $this->imageBlock('inner', 'storage/media/inner.jpg'),
            ]],
        ]);

        preg_match('/<img[^>]*class="site-bg-media"[^>]*>/', $html, $background);
        preg_match('/<img[^>]*alt="Alt inner"[^>]*>/', $html, $inner);
        $this->assertStringContainsString('fetchpriority="high"', $background[0]);
        $this->assertStringContainsString('loading="lazy"', $inner[0]);
    }

    public function test_no_priority_below_a_header_image_or_in_regions(): void
    {
        $html = app(BlockRenderer::class)->render([$this->imageBlock('a')], prioritizeFirstImage: false);

        $this->assertStringNotContainsString('fetchpriority', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function test_editor_canvas_still_marks_image_blocks(): void
    {
        EditorCanvas::enable();
        try {
            $html = app(BlockRenderer::class)->render([$this->imageBlock('img1')]);
        } finally {
            EditorCanvas::enable(false);
        }

        $this->assertMatchesRegularExpression('/^\s*<figure data-pb-id="img1" data-pb-type="image"/', $html);
    }

    public function test_page_with_header_image_gives_it_the_priority(): void
    {
        $this->mediaWithVariants('media/2026/10/header.jpg');
        Page::create([
            'title' => 'With header',
            'slug' => 'with-header',
            'is_active' => true,
            'content_type' => 'blocks',
            'header_image' => 'storage/media/2026/10/header.jpg',
            'blocks' => [$this->imageBlock('a')],
        ]);

        $html = $this->get('/with-header')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        preg_match('/<img[^>]*alt="Mountains"[^>]*>/', $html, $header);
        $this->assertNotEmpty($header, 'Header image with the alt text of the library');
        $this->assertStringContainsString('fetchpriority="high"', $header[0]);
        $this->assertStringContainsString('srcset=', $header[0]);
    }
}
