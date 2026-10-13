<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Models\Page;
use App\Models\Revision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * Revisions for translations too: the German version has its own history,
 * can be compared and restored — without touching the original
 * language.
 *
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class TranslationRevisionTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->seedPermissions();
        $this->disableTwoFactor();
        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
    }

    private function page(): Page
    {
        $page = Page::factory()->create(['title' => 'About us', 'slug' => 'about-us']);
        $page->saveTranslations('de', ['title' => 'Über uns', 'slug' => 'ueber-uns']);

        return $page;
    }

    public function test_saving_a_translation_keeps_the_previous_state(): void
    {
        $this->actingAs($this->createAdmin());
        $page = $this->page();   // first version: nothing existed before

        $this->travel(15)->minutes();
        $page->saveTranslations('de', ['title' => 'Wir über uns']);

        $revision = $page->revisions()->first();
        $this->assertSame('de', $revision->data['_locale']);
        $this->assertSame('Über uns', $revision->data['title']);
        // The original language stays untouched
        $this->assertSame('About us', $page->fresh()->getOriginalValue('title'));
    }

    public function test_sessions_are_counted_per_language(): void
    {
        $this->actingAs($this->createAdmin());
        $page = $this->page();
        $this->travel(15)->minutes();

        $page->saveTranslations('de', ['title' => 'Eins']);
        $page->saveTranslations('de', ['title' => 'Zwei']);    // same session
        $page->update(['title' => 'About']);                     // other language: own revision

        $locales = $page->revisions()->get()->map(fn (Revision $r) => $r->data['_locale'] ?? 'base')->all();
        $this->assertSame(1, count(array_keys($locales, 'de')));
        $this->assertSame(1, count(array_keys($locales, 'base')));
    }

    public function test_restoring_a_translation_revision_restores_only_that_language(): void
    {
        $this->actingAs($this->createAdmin());
        $page = $this->page();
        $this->travel(15)->minutes();
        $page->saveTranslations('de', ['title' => 'Neu', 'subtitle' => 'Später ergänzt']);

        $revision = $page->revisions()->get()->first(fn (Revision $r) => ($r->data['_locale'] ?? null) === 'de');
        $this->post(route('backend.pages.revisions.restore', [$page, $revision]))
            ->assertRedirect(route('backend.pages.edit', ['page' => $page, 'tab' => 'revisions', 'lang' => 'de']));

        $de = $page->fresh()->translationsFor('de');
        $this->assertSame('Über uns', $de['title']);
        // What did not exist back then falls back to the original again
        $this->assertArrayNotHasKey('subtitle', array_filter($de));
        $this->assertSame('About us', $page->fresh()->getOriginalValue('title'));

        // The state before restoring is saved
        $latest = $page->revisions()->first();
        $this->assertSame('de', $latest->data['_locale']);
        $this->assertSame('Neu', $latest->data['title']);
    }

    public function test_list_shows_language_summary_and_compare_works(): void
    {
        $this->actingAs($this->createAdmin());
        $page = $this->page();
        $this->travel(15)->minutes();
        $page->saveTranslations('de', ['title' => 'Neu']);

        $list = $this->getJson(route('backend.pages.revisions.index', $page))->assertOk()->json('revisions');
        $this->assertSame('DE', $list[0]['locale']);
        $this->assertStringContainsString(__('translation.pages.field_title'), $list[0]['summary']);
        $this->assertFalse($list[0]['previewable']);

        $diff = $this->getJson(route('backend.pages.revisions.show', [$page, $list[0]['id']]))->assertOk()->json();
        $this->assertSame('DE', $diff['locale']);
        $this->assertSame('Über uns', $diff['diff']['title']['revision']);
        $this->assertSame('Neu', $diff['diff']['title']['current']);

        $this->get(route('backend.pages.revisions.preview', [$page, $list[0]['id']]))->assertNotFound();
    }
}
