<?php

namespace Tests\Feature\Media;

use App\Helpers\HtmlHelper;
use App\Models\Media;
use App\Services\BlockRenderer;
use App\Support\ResponsiveImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Responsive images inside rich text: library images get srcset/sizes,
 * width/height, loading and decoding at render time; the stored HTML and
 * external images stay untouched.
 */
class RichTextImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
    }

    private function photo(string $path = 'media/2026/10/photo.jpg', array $attributes = []): Media
    {
        $base = preg_replace('/\.jpg$/', '', $path);

        return Media::factory()->image()->create(array_merge([
            'path' => $path,
            'width' => 2000,
            'height' => 1000,
            'variants' => [
                ['width' => 480, 'height' => 240, 'path' => $base.'-480w.webp', 'mime' => 'image/webp'],
                ['width' => 1280, 'height' => 640, 'path' => $base.'-1280w.webp', 'mime' => 'image/webp'],
            ],
        ], $attributes));
    }

    /** The first <img> tag of the markup */
    private function img(string $html, int $index = 0): string
    {
        preg_match_all('/<img\b[^>]*>/', $html, $m);
        $this->assertArrayHasKey($index, $m[0], 'No <img> #'.$index.' in: '.$html);

        return $m[0][$index];
    }

    public function test_library_image_gets_srcset_sizes_and_dimensions(): void
    {
        $this->photo();

        $tag = $this->img(HtmlHelper::clean('<p><img src="storage/media/2026/10/photo.jpg" alt="Mountains" class="mx-auto"></p>'));

        $this->assertStringContainsString('src="'.asset('storage/media/2026/10/photo.jpg').'"', $tag);
        $this->assertStringContainsString('alt="Mountains"', $tag);
        $this->assertStringContainsString('class="mx-auto"', $tag);
        $this->assertStringContainsString('srcset="'.e(ResponsiveImage::srcset(Media::firstOrFail())).'"', $tag);
        $this->assertStringContainsString('photo-480w.webp 480w', $tag);
        // Wider than the content: the content width
        $this->assertStringContainsString('sizes="auto, '.ResponsiveImage::DEFAULT_SIZES.'"', $tag);
        $this->assertStringContainsString('width="2000"', $tag);
        $this->assertStringContainsString('height="1000"', $tag);
        $this->assertStringContainsString('loading="lazy"', $tag);
        $this->assertStringContainsString('decoding="async"', $tag);
        $this->assertStringNotContainsString('fetchpriority', $tag);
    }

    public function test_relative_and_absolute_library_urls_are_recognised(): void
    {
        $this->photo();

        foreach (['/storage/media/2026/10/photo.jpg', asset('storage/media/2026/10/photo.jpg')] as $src) {
            $this->assertStringContainsString('srcset=', $this->img(HtmlHelper::clean('<img src="'.$src.'" alt="x">')));
        }
    }

    public function test_external_and_unknown_images_are_unchanged(): void
    {
        $this->photo();

        foreach ([
            '<p><img src="https://example.org/cat.jpg" alt="Cat"></p>',
            '<p><img src="storage/media/2026/10/unknown.jpg" alt="Unknown"></p>',
            '<p>No images at all</p>',
        ] as $html) {
            $this->assertSame(HtmlHelper::clean($html, responsiveImages: false), HtmlHelper::clean($html));
        }
    }

    public function test_the_stored_html_is_not_changed(): void
    {
        $this->photo();
        $html = '<p><img src="storage/media/2026/10/photo.jpg" alt="Mountains"></p>';

        $block = ['id' => 't', 'type' => 'text', 'data' => ['content' => $html], 'settings' => []];
        $rendered = app(BlockRenderer::class)->render([$block]);

        $this->assertStringContainsString('srcset=', $rendered);
        $this->assertSame('<p><img src="storage/media/2026/10/photo.jpg" alt="Mountains"></p>', $block['data']['content']);
    }

    public function test_width_set_by_the_editor_is_respected(): void
    {
        $this->photo();

        $tag = $this->img(HtmlHelper::clean('<img src="storage/media/2026/10/photo.jpg" alt="Small" width="300">'));

        $this->assertStringContainsString('width="300"', $tag);
        $this->assertStringNotContainsString('width="2000"', $tag);
        // Height from the aspect ratio of the original
        $this->assertStringContainsString('height="150"', $tag);
        $this->assertStringContainsString('sizes="auto, (min-width: 300px) 300px, 100vw"', $tag);

        $both = $this->img(HtmlHelper::clean('<img src="storage/media/2026/10/photo.jpg" alt="Both" width="400" height="400">'));
        $this->assertSame(1, substr_count($both, 'width='));
        $this->assertStringContainsString('width="400"', $both);
        $this->assertSame(1, substr_count($both, 'height='));
        $this->assertStringContainsString('height="400"', $both);
    }

    public function test_eager_loading_set_by_the_editor_stays(): void
    {
        $this->photo();

        $tag = $this->img(HtmlHelper::clean('<img src="storage/media/2026/10/photo.jpg" alt="Eager" loading="eager">'));

        $this->assertSame(1, substr_count($tag, 'loading='));
        $this->assertStringContainsString('loading="eager"', $tag);
        $this->assertStringNotContainsString('sizes="auto', $tag);
    }

    public function test_small_images_use_their_own_width_as_sizes(): void
    {
        $this->photo('media/2026/10/small.jpg', ['width' => 900, 'height' => 600, 'variants' => [
            ['width' => 480, 'height' => 320, 'path' => 'media/2026/10/small-480w.webp', 'mime' => 'image/webp'],
        ]]);

        $tag = $this->img(HtmlHelper::clean('<img src="storage/media/2026/10/small.jpg" alt="Small">'));

        $this->assertStringContainsString('sizes="auto, (min-width: 900px) 900px, 100vw"', $tag);
    }

    public function test_added_attribute_values_are_escaped(): void
    {
        $this->photo('media/2026/10/evil.jpg', ['variants' => [
            ['width' => 480, 'height' => 240, 'path' => 'media/2026/10/a"onerror="alert(1)<b>.webp', 'mime' => 'image/webp'],
        ]]);

        $html = HtmlHelper::clean('<p><img src="storage/media/2026/10/evil.jpg" alt="&quot; onload=&quot;x"></p><p onclick="alert(1)">Text</p>');

        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringNotContainsString('"onerror="', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('" onload="', $html);
        $this->assertSame(1, substr_count($html, '<img'));

        // The result parses back to a single <img> whose attributes are exactly the expected ones
        $doc = new \DOMDocument;
        @$doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
        $img = $doc->getElementsByTagName('img')->item(0);
        $names = [];
        foreach ($img->attributes as $attribute) {
            $names[] = $attribute->name;
        }
        sort($names);
        $this->assertSame(['alt', 'decoding', 'height', 'loading', 'sizes', 'src', 'srcset', 'width'], $names);
        $this->assertSame('" onload="x', $img->getAttribute('alt'));
        $this->assertStringContainsString('a"onerror="alert(1)<b>.webp 480w', $img->getAttribute('srcset'));
    }

    public function test_utf8_content_is_preserved(): void
    {
        $this->photo();
        $text = '<p>Crème brûlée – naïve café, Ελληνικά, 日本語, emoji 😀 &amp; non-breaking&nbsp;space</p>';

        $html = HtmlHelper::clean($text.'<p><img src="storage/media/2026/10/photo.jpg" alt="Café ☕"></p>');

        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('alt="Café ☕"', $html);
        $this->assertStringStartsWith(HtmlHelper::clean($text, responsiveImages: false), $html);
        $this->assertStringContainsString('Crème brûlée – naïve café, Ελληνικά, 日本語, emoji 😀', $html);
    }

    public function test_multiple_images_need_one_query(): void
    {
        $this->photo('media/2026/10/a.jpg');
        $this->photo('media/2026/10/b.jpg');
        $this->photo('media/2026/10/c.jpg');

        $html = '<p><img src="storage/media/2026/10/a.jpg" alt="A"><img src="/storage/media/2026/10/b.jpg" alt="B"></p>'
            .'<p><img src="'.asset('storage/media/2026/10/c.jpg').'" alt="C"><img src="storage/media/2026/10/a.jpg" alt="A again">'
            .'<img src="storage/media/2026/10/missing.jpg" alt="Missing"><img src="https://example.org/x.jpg" alt="External"></p>';

        DB::enableQueryLog();
        $result = HtmlHelper::clean($html);
        $queries = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], '"media"'));
        DB::disableQueryLog();

        $this->assertCount(1, $queries, 'One query for all library images of the text');
        $this->assertSame(4, substr_count($result, 'srcset='));
        $this->assertStringNotContainsString('srcset', $this->img($result, 4), 'Unknown file: unchanged');
        $this->assertStringNotContainsString('srcset', $this->img($result, 5), 'External: unchanged');
    }

    public function test_accordion_content_gets_responsive_images_too(): void
    {
        $this->photo();

        $html = app(BlockRenderer::class)->render([[
            'id' => 'a', 'type' => 'accordion', 'settings' => [],
            'data' => ['items' => [['title' => 'Question', 'content' => '<p><img src="storage/media/2026/10/photo.jpg" alt="Answer image"></p>']]],
        ]]);

        $this->assertMatchesRegularExpression('/<img[^>]*alt="Answer image"[^>]*srcset="[^"]+"/', $html);
    }
}
