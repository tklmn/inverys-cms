<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Models\Media;
use App\Models\Page;
use App\Models\Setting;
use App\Support\Locales;
use App\Support\ThemeTranslations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Plugins\FormBuilder\Models\Form;
use Plugins\Gallery\Models\GalleryAlbum;
use Plugins\Multilingual\DatabaseContentTranslator;
use Plugins\Multilingual\Http\Middleware\DetectContentLocale;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class MultilingualTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private Locales $locales;

    private ContentTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();

        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);

        $this->locales = app(Locales::class);
        $this->translator = app(ContentTranslator::class);
    }

    private function makePage(array $attributes = []): Page
    {
        return Page::factory()->create(array_merge([
            'title' => 'About us',
            'slug' => 'about-us',
            'content' => '<p>Original content</p>',
        ], $attributes));
    }

    public function test_plugins_swap_in_database_translator_and_register_german(): void
    {
        $this->assertInstanceOf(DatabaseContentTranslator::class, $this->translator);
        $this->assertTrue($this->locales->has('de'));
        $this->assertTrue($this->locales->isMultilingual());
        $this->assertSame('Deutsch', $this->locales->native('de'));

        // The pack brings its own UI texts; English stays the fallback
        $this->assertNotSame(__('translation.locales.editing', [], 'en'), __('translation.locales.editing', [], 'de'));
        $this->assertSame(__('translation.locales.editing', [], 'en'), __('translation.locales.editing', [], 'fr'));
    }

    public function test_base_locale_is_recorded_on_install(): void
    {
        $this->assertSame('en', Setting::where('key', 'ml.base_locale')->value('value'));
        $this->assertSame('en', $this->locales->base());
    }

    public function test_translated_fields_replace_originals_and_empty_fields_fall_back(): void
    {
        $page = $this->makePage();
        $this->translator->save($page, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns', 'content' => '']);

        $this->assertSame('About us', $page->fresh()->title);

        $this->locales->setVisitor('de');
        $page = $page->fresh();

        $this->assertSame('Über uns', $page->title);
        $this->assertSame('ueber-uns', $page->slug);
        // No translated blocks: the original's content applies
        $this->assertSame('<p>Original content</p>', $page->blocks[0]['data']['content']);
    }

    public function test_page_is_found_by_its_translated_slug(): void
    {
        $page = $this->makePage();
        $this->translator->save($page, 'de', ['slug' => 'ueber-uns']);

        $this->locales->setVisitor('de');

        $this->assertTrue($page->is(Page::findByTranslatedSlug(Page::query(), 'ueber-uns')));
        $this->assertTrue($page->is(Page::findByTranslatedSlug(Page::query(), 'about-us')));
    }

    public function test_missing_translation_falls_back_to_default_language(): void
    {
        $this->locales->register('fr', 'French', 'Français');
        Setting::updateOrCreate(['key' => 'language'], ['value' => 'de']);
        $this->locales->flush();

        $page = $this->makePage();
        $this->translator->save($page, 'de', ['title' => 'Über uns']);

        // French has nothing yet: the default language (German) is shown
        $this->locales->setVisitor('fr');
        $this->assertSame(['fr', 'de'], $this->locales->contentChain());
        $this->assertSame('Über uns', $page->fresh()->title);
    }

    public function test_locale_prefix_serves_the_translation(): void
    {
        Page::factory()->root()->create();
        $page = $this->makePage();
        $this->translator->save($page, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns']);

        $this->get('/about-us')->assertOk()->assertSee('About us');

        $this->get('/de/ueber-uns')
            ->assertOk()
            ->assertSee('Über uns')
            ->assertSee('hreflang="en"', false);
    }

    public function test_default_language_prefix_redirects_to_unprefixed_url(): void
    {
        $this->makePage();

        $response = $this->get('/en/about-us?x=1');

        $response->assertStatus(301);
        $this->assertStringEndsWith('/about-us?x=1', $response->headers->get('Location'));
    }

    public function test_default_language_redirect_ignores_a_foreign_host_header(): void
    {
        // The middleware runs before TrustHosts: the redirect must not
        // carry the Host header over into an absolute address
        $response = $this->get('http://evil.example/en/about-us');

        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString('evil.example', $location);
        $this->assertSame('/about-us', $location);

        // Browsers read "/\host" like "//host" — no redirect to foreign hosts
        $request = Request::create('http://localhost/en/x');
        $request->server->set('REQUEST_URI', '/en/\\evil.example');
        $response = app(DetectContentLocale::class)->handle($request, fn () => response('next'));

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/evil.example', $response->headers->get('Location'));
    }

    public function test_saving_a_translation_leaves_the_original_untouched(): void
    {
        $this->seedPermissions();
        $page = $this->makePage();

        $this->actingAs($this->createAdmin())
            ->put(route('backend.pages.update', $page), ['_lang' => 'de', 'title' => 'Über uns', 'blocks' => json_encode([Page::textBlock('<p>Inhalt</p>', 'b1')])])
            ->assertRedirect(route('backend.pages.edit', ['page' => $page, 'lang' => 'de']));

        $page = $page->fresh();
        $this->assertSame('About us', $page->title);
        $this->assertSame('<p>Original content</p>', $page->blocks[0]['data']['content']);

        // Slug derived from the translated title, with German transliteration;
        // the translated blocks bring their search text along
        $translation = $page->translationsFor('de');
        $this->assertEquals(['title' => 'Über uns', 'content' => 'Inhalt', 'slug' => 'ueber-uns'], array_intersect_key(
            $translation,
            array_flip(['title', 'content', 'slug']),
        ));
        $this->assertSame('<p>Inhalt</p>', json_decode($translation['blocks'], true)[0]['data']['content']);
    }

    public function test_translated_slug_must_be_unique_per_language(): void
    {
        $this->seedPermissions();
        $this->makePage(['title' => 'Contact', 'slug' => 'contact']);
        $page = $this->makePage();

        $this->actingAs($this->createAdmin())
            ->put(route('backend.pages.update', $page), ['_lang' => 'de', 'title' => 'Über uns', 'slug' => 'contact'])
            ->assertSessionHasErrors('slug');

        $this->assertSame([], array_filter($page->fresh()->translationsFor('de')));
    }

    public function test_generated_urls_get_the_visitor_prefix(): void
    {
        $this->locales->setVisitor('de');

        $this->assertStringEndsWith('/de/contact', url('/contact'));
        $this->assertStringEndsWith('/backend', url('/backend'));
    }

    public function test_form_texts_are_overlaid_in_a_translation(): void
    {
        $this->loadPlugins(['form-builder' => 'Form Builder']);

        $form = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'name', 'label' => 'Name', 'placeholder' => 'Your name'],
            ],
            'settings' => ['submit_label' => 'Send'],
        ]);

        $this->translator->save($form, 'de', [
            'field_texts' => json_encode(['f1' => ['label' => 'Ihr Name']]),
            'submit_label' => 'Senden',
        ]);

        $this->locales->setVisitor('de');
        $form = $form->fresh();

        $this->assertSame('Ihr Name', $form->fields[0]['label']);
        $this->assertSame('Your name', $form->fields[0]['placeholder']);
        $this->assertSame('Senden', $form->settings['submit_label']);
    }

    public function test_gallery_captions_are_translated_per_image(): void
    {
        $this->loadPlugins(['gallery' => 'Gallery']);

        $album = GalleryAlbum::create(['name' => 'Album', 'slug' => 'album', 'settings' => [], 'is_active' => true]);
        [$first, $second] = Media::factory()->count(2)->create();
        $album->images()->attach($first->id, ['sort_order' => 0, 'caption' => 'Sunset']);
        $album->images()->attach($second->id, ['sort_order' => 1, 'caption' => 'Beach']);

        $this->translator->save($album, 'de', ['captions' => json_encode([(string) $first->id => 'Sonnenuntergang'])]);

        $this->locales->setVisitor('de');
        $album = $album->fresh();
        [$first, $second] = $album->images;

        $this->assertSame('Sonnenuntergang', $album->captionFor($first));
        $this->assertSame('Beach', $album->captionFor($second));
    }

    public function test_theme_texts_are_translated(): void
    {
        ThemeTranslations::save('theme.default.footer_text', 'de', 'Alle Rechte vorbehalten.', 'All rights reserved.', 'theme_setting');

        $this->assertSame('All rights reserved.', ThemeTranslations::setting('default', 'footer_text', 'All rights reserved.'));

        $this->locales->setVisitor('de');

        $this->assertSame('Alle Rechte vorbehalten.', ThemeTranslations::setting('default', 'footer_text', 'All rights reserved.'));
        // Colors are not translatable
        $this->assertSame('#123456', ThemeTranslations::setting('default', 'primary_color', '#123456'));
    }
}
