<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * One address per page and language: translated slugs win, hreflang and
 * og:locale only for languages with a translation, BCP 47 codes.
 *
 * German fixtures on purpose (german-fixtures): these tests cover German slugs.
 */
class MultilingualSeoTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private ContentTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
        $this->translator = app(ContentTranslator::class);
    }

    /**
     * The test client builds its URLs with url() — which carries the
     * language prefix of the previous request's visitor. A new visitor has none.
     */
    private function asNewVisitor(): void
    {
        app(Locales::class)->setVisitor(null);
    }

    private function translatedPage(): Page
    {
        $page = Page::factory()->create(['title' => 'About us', 'slug' => 'about-us', 'meta_description' => 'Who we are']);
        $this->translator->save($page, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns', 'meta_description' => 'Wer wir sind']);

        return $page;
    }

    public function test_original_slug_in_another_language_redirects_to_the_translated_one(): void
    {
        $this->translatedPage();

        $this->get('/de/about-us?x=1')->assertStatus(301)->assertRedirect('http://localhost/de/ueber-uns?x=1');
        $this->get('/de/ueber-uns')->assertOk();
    }

    public function test_hreflang_lists_the_languages_with_a_translation(): void
    {
        $this->translatedPage();

        $html = $this->get('/de/ueber-uns')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/about-us">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="de" href="http://localhost/de/ueber-uns">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://localhost/about-us">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/de/ueber-uns">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="de_DE">', $html);
        $this->assertStringContainsString('<meta property="og:locale:alternate" content="en_US">', $html);
        $this->assertStringContainsString('<meta name="description" content="Wer wir sind">', $html);
    }

    public function test_untranslated_page_has_no_hreflang_and_points_to_the_original(): void
    {
        Page::factory()->create(['title' => 'Imprint', 'slug' => 'imprint']);

        $html = $this->get('/de/imprint')->assertOk()->getContent();

        $this->assertStringNotContainsString('<link rel="alternate" hreflang=', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/imprint">', $html);
        // The content shown is the English original
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);

        $this->asNewVisitor();
        $this->assertStringNotContainsString('<link rel="alternate" hreflang=', $this->get('/imprint')->getContent());
    }

    public function test_hreflang_codes_use_hyphens(): void
    {
        app(Locales::class)->register('pt_BR', 'Portuguese (Brazil)', 'Português');
        $page = $this->translatedPage();
        $this->translator->save($page, 'pt_BR', ['title' => 'Sobre nós', 'slug' => 'sobre-nos']);

        $html = $this->get('/pt_BR/sobre-nos')->assertOk()->getContent();
        $this->assertStringContainsString('hreflang="pt-BR" href="http://localhost/pt_BR/sobre-nos"', $html);
        $this->assertStringNotContainsString('hreflang="pt_BR"', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="pt_BR">', $html);

        $sitemap = $this->get('/sitemap-pt_BR.xml')->assertOk()->getContent();
        $this->assertStringContainsString('hreflang="pt-BR"', $sitemap);
        $this->assertStringNotContainsString('hreflang="pt_BR"', $sitemap);
    }

    public function test_changing_a_translated_slug_adds_a_redirect_for_that_language(): void
    {
        $page = $this->translatedPage();

        $page->saveTranslations('de', ['slug' => 'wir']);

        $redirect = Redirect::sole();
        $this->assertSame('/ueber-uns', $redirect->source_path);
        $this->assertSame('de', $redirect->locale);
        $this->get('/de/ueber-uns')->assertStatus(301)->assertRedirect('http://localhost/de/wir');
        // Only in German: the English address is untouched
        $this->asNewVisitor();
        $this->get('/ueber-uns')->assertNotFound();
    }

    public function test_social_fields_and_canonical_url_are_translatable(): void
    {
        $page = $this->translatedPage();

        $this->actingAs($this->createAdmin())
            ->put(route('backend.pages.update', $page), [
                '_lang' => 'de',
                'title' => 'Über uns',
                'slug' => 'ueber-uns',
                'og_title' => 'Lernen Sie uns kennen',
                'canonical_url' => 'https://example.org/de/team',
            ])
            ->assertSessionHasNoErrors();

        $translation = $page->fresh()->translationsFor('de');
        $this->assertSame('Lernen Sie uns kennen', $translation['og_title']);
        $this->assertSame('https://example.org/de/team', $translation['canonical_url']);
        $this->assertNull($page->fresh()->getOriginalValue('canonical_url'));

        $html = $this->get('/de/ueber-uns')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://example.org/de/team">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Lernen Sie uns kennen">', $html);
        // The canonical address lies elsewhere: no language links for this URL
        $this->assertStringNotContainsString('<link rel="alternate" hreflang=', $html);
    }

    public function test_sitemap_lastmod_follows_a_later_translation(): void
    {
        $page = Page::factory()->create(['title' => 'About us', 'slug' => 'about-us', 'updated_at' => '2026-01-01 10:00:00']);
        Page::withoutEvents(fn () => Page::whereKey($page->id)->update(['updated_at' => '2026-01-01 10:00:00']));
        $this->travelTo('2026-03-04 05:06:07');
        $this->translator->save($page, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns']);

        $de = $this->get('/sitemap-de.xml')->assertOk()->getContent();
        $en = $this->get('/sitemap-en.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<lastmod>2026-03-04T05:06:07', $de);
        $this->assertStringContainsString('<lastmod>2026-01-01T10:00:00', $en);
    }
}
