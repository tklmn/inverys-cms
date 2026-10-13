<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\InstallerLanguages;
use Database\Seeders\SettingsTableSeeder;
use Database\Seeders\StarterContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The installer's starter content (home page, footer, copyright) — above all
 * for installations without sample pages: no links to /about or /contact,
 * no Tailwind classes, texts in the installation's language.
 */
class StarterContentTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> all link targets in a block tree */
    private function urls(array $blocks): array
    {
        $urls = [];
        array_walk_recursive($blocks, function ($value, $key) use (&$urls) {
            if (in_array($key, ['url', 'primary_url', 'secondary_url'], true) && $value !== '') {
                $urls[] = $value;
            }
        });

        return $urls;
    }

    public function test_home_links_only_to_targets_every_installation_has(): void
    {
        $blocks = StarterContent::homeBlocks('Test Site', '/blog');

        $this->assertSame([], array_diff($this->urls($blocks), ['/backend', '/blog']));
        $this->assertSame('hero', $blocks[0]['type']);
        $this->assertStringStartsWith(StarterContent::HOME_PREFIX.'-', $blocks[0]['id']);
    }

    public function test_home_is_styled_with_builder_options_not_tailwind_classes(): void
    {
        $json = json_encode(StarterContent::homeBlocks('Test Site', '/blog'));

        $this->assertStringNotContainsString('bg-linear', $json);
        $this->assertStringNotContainsString('dark:', $json);
        $this->assertStringNotContainsString('text-gray', $json);
        $this->assertDoesNotMatchRegularExpression('/"class":"[^"]+"/', $json);
    }

    public function test_block_ids_are_stable_so_translations_match(): void
    {
        $ids = fn (array $blocks) => array_column($blocks, 'id');

        $this->assertSame($ids(StarterContent::homeBlocks('A', '/blog')), $ids(StarterContent::homeBlocks('B', '/blog')));
    }

    public function test_texts_follow_the_installation_language(): void
    {
        $this->assertTrue(InstallerLanguages::use('de'));

        $hero = StarterContent::homeBlocks('Test Site', '/blog')[0];

        $this->assertSame('Willkommen bei Test Site', $hero['data']['title']);
        $this->assertSame('Startseite', StarterContent::homeTitle());
        $this->assertStringContainsString('Alle Rechte vorbehalten', StarterContent::copyright('Test Site'));
    }

    public function test_without_sample_pages_the_footer_drops_demo_links(): void
    {
        $this->seed(SettingsTableSeeder::class);

        StarterContent::applyThemeTexts('Test Site', '/blog', replaceFooter: true);

        $footer = json_decode(Setting::where('key', 'theme.default.region.footer_content')->value('value'), true);
        $this->assertSame(['/', '/blog'], $this->urls($footer));
        $this->assertStringNotContainsString('hello@inverys.com', json_encode($footer));

        $this->assertSame('© '.date('Y').' Test Site. All rights reserved.', Setting::where('key', 'theme.default.footer_text')->value('value'));
    }

    public function test_with_sample_pages_the_theme_footer_stays(): void
    {
        $this->seed(SettingsTableSeeder::class);

        StarterContent::applyThemeTexts('Test Site', '/blog', replaceFooter: false);

        $footer = json_decode(Setting::where('key', 'theme.default.region.footer_content')->value('value'), true);
        $this->assertContains('/about', $this->urls($footer));
    }
}
