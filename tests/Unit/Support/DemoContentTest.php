<?php

namespace Tests\Unit\Support;

use App\Support\BlockDesign;
use App\Support\ContentIcons;
use Database\Seeders\Demo\DemoIllustrations;
use Database\Seeders\Demo\DemoPages;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The demo pages show what editors can do themselves — so only block
 * options, no Tailwind classes.
 */
class DemoContentTest extends TestCase
{
    public static function pages(): array
    {
        return array_map(fn ($slug) => [$slug], array_combine(array_keys(DemoPages::PAGES), array_keys(DemoPages::PAGES)));
    }

    /** @return iterable<array> */
    private function walk(array $blocks): iterable
    {
        foreach ($blocks as $block) {
            yield $block;
            yield from $this->walk($block['children'] ?? []);
        }
    }

    #[DataProvider('pages')]
    public function test_demo_page_uses_only_block_options(string $slug): void
    {
        $ids = [];

        foreach ($this->walk(DemoPages::for($slug)) as $block) {
            $this->assertArrayHasKey('id', $block);
            $ids[] = $block['id'];

            $settings = $block['settings'] ?? [];
            $this->assertSame('', (string) ($settings['class'] ?? ''), "{$slug}: {$block['id']} has CSS classes");
            $this->assertSame('', (string) ($settings['background'] ?? ''), "{$slug}: {$block['id']} has a background class");

            if (isset($settings['style']) && in_array($block['type'], ['container', 'section'], true) && $settings['style'] !== '') {
                $this->assertContains($settings['style'], BlockDesign::SURFACES);
            }
            if (! empty($block['data']['icon'])) {
                $this->assertTrue(ContentIcons::has($block['data']['icon']), "{$slug}: unknown icon {$block['data']['icon']}");
            }
            if (($block['type'] ?? '') === 'text') {
                $this->assertStringNotContainsString('class=', $block['data']['content'] ?? '');
            }
        }

        $this->assertSame(count($ids), count(array_unique($ids)), "{$slug}: duplicate block ids");
    }

    public function test_every_page_has_at_most_one_h1(): void
    {
        foreach (array_keys(DemoPages::PAGES) as $slug) {
            $h1 = 0;
            foreach ($this->walk(DemoPages::for($slug)) as $block) {
                $h1 += (int) ($block['type'] === 'hero' && ($block['settings']['level'] ?? 'h1') === 'h1');
                $h1 += (int) ($block['type'] === 'heading' && ($block['data']['level'] ?? '') === 'h1');
            }
            $this->assertLessThanOrEqual(1, $h1, "{$slug} has {$h1} h1 headings");
        }
    }

    #[DataProvider('pages')]
    public function test_every_jump_link_has_a_target_on_the_page(string $slug): void
    {
        $anchors = [];
        $links = [];

        foreach ($this->walk(DemoPages::for($slug)) as $block) {
            if (! empty($block['settings']['anchor'])) {
                $anchors[] = BlockDesign::anchor($block['settings']['anchor']);
            }
            foreach (['url', 'primary_url', 'secondary_url'] as $key) {
                $url = (string) ($block['data'][$key] ?? '');
                if (strlen($url) > 1 && $url[0] === '#') {
                    $links[] = substr($url, 1);
                }
            }
        }

        $missing = array_values(array_diff($links, $anchors));
        $this->assertSame([], $missing, "{$slug}: jump links without target");
    }

    #[DataProvider('pages')]
    public function test_internal_links_point_to_existing_demo_pages(string $slug): void
    {
        // Page paths are single-level (/web-development, not /services/web-development)
        $known = array_merge(array_keys(DemoPages::PAGES), ['privacy-policy', 'terms-of-service', 'blog', 'login', 'register']);
        $broken = [];

        foreach ($this->walk(DemoPages::for($slug)) as $block) {
            foreach (['url', 'primary_url', 'secondary_url'] as $key) {
                $url = (string) ($block['data'][$key] ?? '');
                if (str_starts_with($url, '/') && $url !== '/' && ! in_array(ltrim($url, '/'), $known, true)) {
                    $broken[] = $url;
                }
            }
        }

        $this->assertSame([], $broken, "{$slug}: links to pages that do not exist");
    }

    public function test_every_child_page_has_blocks(): void
    {
        foreach (DemoPages::CHILDREN as $parent => $children) {
            $this->assertArrayHasKey($parent, DemoPages::PAGES);
            foreach (array_keys($children) as $slug) {
                $this->assertNotEmpty(DemoPages::for($slug), "{$slug} has no blocks");
            }
        }
    }

    public function test_illustrations_are_valid_svg_without_text(): void
    {
        foreach (DemoIllustrations::files() as $file) {
            $svg = DemoIllustrations::svg($file);

            $this->assertNotFalse(simplexml_load_string($svg), "{$file} is not valid XML");
            $this->assertStringNotContainsString('<text', $svg, "{$file} contains text");
        }
    }
}
