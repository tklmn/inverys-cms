<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Models\Page;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\LanguageDe\DemoTranslations;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class DemoTranslationsTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
    }

    private function demoPage(): Page
    {
        return Page::factory()->create([
            'title' => 'About Us',
            'slug' => 'about',
            'meta_title' => 'About Us | Inverys CMS',
            'content_type' => 'blocks',
            'blocks' => [
                ['id' => 'c1', 'type' => 'container', 'settings' => [], 'children' => [
                    ['id' => 'h1', 'type' => 'heading', 'data' => ['text' => 'Our Story', 'level' => 'h2'], 'settings' => []],
                    ['id' => 'x1', 'type' => 'heading', 'data' => ['text' => 'Custom text nobody translated', 'level' => 'h3'], 'settings' => []],
                ]],
            ],
        ]);
    }

    public function test_demo_content_gets_a_german_version(): void
    {
        $page = $this->demoPage();

        $counts = (new DemoTranslations)->apply();
        $this->assertSame(1, $counts['pages'] ?? 0);

        // Original unchanged
        $this->assertSame('About Us', $page->fresh()->title);

        app(Locales::class)->setVisitor('de');
        $page = $page->fresh();

        $this->assertSame('Über uns', $page->title);
        $this->assertSame('ueber-uns', $page->slug);

        $children = $page->blocks[0]['children'];
        $this->assertSame('Unsere Geschichte', $children[0]['data']['text']);
        $this->assertSame('h1', $children[0]['id'], 'Structure and IDs are kept');
        $this->assertSame('Custom text nobody translated', $children[1]['data']['text']);
    }

    public function test_existing_translations_are_never_overwritten(): void
    {
        $page = $this->demoPage();
        app(ContentTranslator::class)->save($page, 'de', ['title' => 'Wer wir sind']);

        (new DemoTranslations)->apply();

        $this->assertSame('Wer wir sind', $page->fresh()->translationsFor('de')['title']);
        $this->assertSame([], (new DemoTranslations)->apply(), 'A second run changes nothing');
    }

    public function test_every_demo_text_is_translated(): void
    {
        $data = json_decode(file_get_contents(base_path('plugins/language-de/demo/de.json')), true);

        $this->assertGreaterThan(400, count($data['texts']));
        foreach ($data['texts'] as $en => $de) {
            $this->assertNotSame('', trim($de), "Empty translation for: {$en}");
        }
    }
}
