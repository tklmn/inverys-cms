<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Contracts\SettingRepository;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageCategory;
use App\Models\Setting;
use App\Search\PageSearchProvider;
use App\Support\Locales;
use App\Support\SettingTranslations;
use App\Support\TranslatableSettings;
use App\Support\TranslationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Plugins\Gallery\Models\GalleryAlbum;
use Plugins\Gallery\Search\GallerySearchProvider;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * What is translatable beyond pages and posts: settings, media, page
 * categories — and that search and lists know about the translations.
 *
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class TranslatableContentTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private Locales $locales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->seedPermissions();

        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
        $this->locales = app(Locales::class);
    }

    public function test_site_name_is_translated_for_visitors_only(): void
    {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'My Site']);
        SettingTranslations::save('site_name', 'de', 'Meine Website', 'My Site');

        $this->assertSame('My Site', setting('site_name'));

        $this->locales->setVisitor('de');
        $this->assertSame('Meine Website', setting('site_name'));
        $this->assertSame('Meine Website', app(SettingRepository::class)->get('site_name'));
    }

    public function test_unregistered_settings_are_never_translated(): void
    {
        Setting::updateOrCreate(['key' => 'timezone'], ['value' => 'UTC']);
        SettingTranslations::save('timezone', 'de', 'Europe/Berlin', 'UTC');

        $this->locales->setVisitor('de');
        $this->assertSame('UTC', setting('timezone'));
    }

    public function test_list_settings_are_merged_entry_by_entry(): void
    {
        app(TranslatableSettings::class)->register('test.services', [
            'type' => 'list',
            'fields' => ['description' => 'Description'],
        ]);
        $services = [
            ['id' => 'analytics', 'name' => 'Analytics', 'description' => 'Counts visits'],
            ['id' => 'maps', 'name' => 'Maps', 'description' => 'Shows maps'],
        ];
        Setting::updateOrCreate(['key' => 'test.services'], ['value' => json_encode($services)]);
        SettingTranslations::save('test.services', 'de', json_encode(['analytics' => ['description' => 'Zählt Besuche']]), '');

        $this->locales->setVisitor('de');
        $merged = json_decode(app(SettingRepository::class)->getByPrefix('test.')['services'], true);

        $this->assertSame('Zählt Besuche', $merged[0]['description']);
        $this->assertSame('Analytics', $merged[0]['name']);
        $this->assertSame('Shows maps', $merged[1]['description']);
    }

    public function test_texts_page_saves_translations_and_leaves_originals(): void
    {
        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'My Site']);
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get(route('backend.multilingual.texts'))
            ->assertRedirect(route('backend.multilingual.texts', ['lang' => 'de']));

        $this->actingAs($admin)->get(route('backend.multilingual.texts', ['lang' => 'de']))
            ->assertOk()
            ->assertSee('name="texts[site_name]"', false);

        $this->actingAs($admin)
            ->put(route('backend.multilingual.texts.update'), ['_lang' => 'de', 'texts' => ['site_name' => 'Meine Website']])
            ->assertRedirect(route('backend.multilingual.texts', ['lang' => 'de']));

        $this->assertSame('Meine Website', SettingTranslations::translated('site_name', 'de'));
        $this->assertSame('My Site', Setting::where('key', 'site_name')->value('value'));

        // Clearing it removes the translation again
        $this->actingAs($admin)
            ->put(route('backend.multilingual.texts.update'), ['_lang' => 'de', 'texts' => ['site_name' => '']]);
        $this->assertNull(SettingTranslations::translated('site_name', 'de'));
    }

    public function test_media_alt_text_is_translated_without_touching_the_original(): void
    {
        $media = Media::factory()->create(['name' => 'Beach', 'alt_text' => 'A beach', 'description' => 'Summer']);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.media.update', $media), ['_lang' => 'de', 'alt_text' => 'Ein Strand'])
            ->assertRedirect(route('backend.media.edit', ['media' => $media, 'lang' => 'de']));

        $media = $media->fresh();
        $this->assertSame('A beach', $media->alt_text);

        $this->locales->setVisitor('de');
        $media = $media->fresh();
        $this->assertSame('Ein Strand', $media->alt_text);
        $this->assertSame('Summer', $media->description);
    }

    public function test_page_category_name_is_translatable(): void
    {
        $category = PageCategory::create(['name' => 'News', 'slug' => 'news']);

        $this->actingAs($this->createAdmin())
            ->put(route('backend.page-categories.update', $category), ['_lang' => 'de', 'name' => 'Neuigkeiten'])
            ->assertRedirect();

        $this->assertSame('News', $category->fresh()->name);

        $this->locales->setVisitor('de');
        $this->assertSame('Neuigkeiten', $category->fresh()->name);
    }

    public function test_search_finds_translated_text_in_the_visitor_language(): void
    {
        $page = Page::factory()->create(['title' => 'About us', 'slug' => 'about-us', 'content' => '<p>Company</p>', 'include_in_search' => true]);
        app(ContentTranslator::class)->save($page, 'de', ['title' => 'Über uns', 'slug' => 'ueber-uns']);
        $flags = ['title' => true, 'content' => true, 'meta' => true];

        // In the base language the search only knows the original
        $this->assertCount(0, (new PageSearchProvider)->search('Über', $flags));

        $this->locales->setVisitor('de');
        $results = (new PageSearchProvider)->search('Über', $flags);

        $this->assertCount(1, $results);
        $this->assertSame('Über uns', $results->first()->title);
        $this->assertStringEndsWith('/de/ueber-uns', $results->first()->url);
    }

    public function test_translation_status_marks_translated_languages(): void
    {
        $this->locales->register('fr', 'French', 'Français');
        $translated = Page::factory()->create();
        $untouched = Page::factory()->create();
        app(ContentTranslator::class)->save($translated, 'de', ['title' => 'Titel']);

        $this->assertSame(['de' => true, 'fr' => false], TranslationStatus::for($translated));
        $this->assertSame(['de' => false, 'fr' => false], TranslationStatus::for($untouched));
    }

    public function test_gallery_search_results_are_only_for_editors(): void
    {
        $this->loadPlugins(['gallery' => 'Gallery']);
        GalleryAlbum::create(['name' => 'Summer', 'slug' => 'summer', 'settings' => [], 'is_active' => true]);
        $flags = ['name' => true, 'description' => true];

        // The hits lead into the backend — visitors do not see them
        $this->assertCount(0, (new GallerySearchProvider)->search('Summer', $flags));

        $this->actingAs($this->createAdmin());
        $this->assertCount(1, (new GallerySearchProvider)->search('Summer', $flags));
    }
}
