<?php

namespace Tests\Feature\Plugins\Multilingual;

use App\Contracts\ContentTranslator;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Locales;
use App\Support\SettingTranslations;
use App\Support\TranslationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Plugins\Multilingual\DatabaseContentTranslator;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

/**
 * Don't start from scratch: an existing translation as a template for a new
 * language — per entry in the editor and for a whole language.
 *
 * German fixtures on purpose (german-fixtures): these tests cover German content, slugs and transliteration.
 */
class TranslationCopyTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    private Locales $locales;

    private DatabaseContentTranslator $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markAsInstalled();
        $this->seedPermissions();

        $this->loadPlugins(['language-de' => 'German', 'multilingual' => 'Multilingual']);
        $this->locales = app(Locales::class);
        $this->locales->register('fr', 'French', 'Français');
        $this->translator = app(ContentTranslator::class);
    }

    private function germanPage(): Page
    {
        $page = Page::factory()->create(['title' => 'About us', 'slug' => 'about-us']);
        $page->saveTranslations('de', [
            'title' => 'Über uns',
            'slug' => 'ueber-uns',
            'blocks' => [Page::textBlock('<p>Hallo</p>', 'b1')],
        ]);

        return $page;
    }

    private function copyUrl(Page $page, User $user, string $from = 'de', string $to = 'fr'): string
    {
        return URL::temporarySignedRoute('backend.translations.copy', now()->addHour(), [
            'type' => Page::class,
            'id' => $page->id,
            'from' => $from,
            'to' => $to,
            'user' => $user->id,
            'back' => '/backend/pages/'.$page->id.'/edit?lang='.$to,
        ], absolute: false);
    }

    public function test_copy_takes_everything_but_the_slug_and_marks_a_draft(): void
    {
        $page = $this->germanPage();

        $copied = $this->translator->copy($page, 'de', 'fr');

        $fr = $page->translationsFor('fr');
        $this->assertSame('Über uns', $fr['title']);
        $this->assertSame('<p>Hallo</p>', json_decode($fr['blocks'], true)[0]['data']['content']);
        $this->assertArrayNotHasKey('slug', $fr);
        $this->assertArrayNotHasKey(DatabaseContentTranslator::DRAFT_FIELD, $fr);
        $this->assertSame(count($fr), $copied);
        $this->assertSame('de', $page->translationDraftOf('fr'));
        $this->assertNull($page->translationDraftOf('de'));
    }

    public function test_copy_never_overwrites_an_existing_translation(): void
    {
        $page = $this->germanPage();
        $page->saveTranslations('fr', ['title' => 'À propos']);

        $this->translator->copy($page, 'de', 'fr');

        $fr = $page->translationsFor('fr');
        $this->assertSame('À propos', $fr['title']);
        $this->assertArrayHasKey('blocks', $fr);
    }

    public function test_saving_in_the_editor_ends_the_draft_but_autosaving_blocks_does_not(): void
    {
        $page = $this->germanPage();
        $this->translator->copy($page, 'de', 'fr');
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->postJson(route('backend.pages.save-blocks', $page).'?lang=fr', ['blocks' => json_encode([Page::textBlock('<p>Bonjour</p>', 'b1')])])
            ->assertOk();
        $this->assertSame('de', $page->translationDraftOf('fr'));
        $this->assertSame(['fr'], TranslationStatus::drafts($page));

        $this->actingAs($admin)
            ->put(route('backend.pages.update', $page), ['_lang' => 'fr', 'title' => 'À propos'])
            ->assertRedirect();
        $this->assertNull($page->fresh()->translationDraftOf('fr'));
        $this->assertSame('a-propos', $page->translationsFor('fr')['slug']);
    }

    public function test_editor_offers_copying_from_an_existing_translation(): void
    {
        $page = $this->germanPage();

        $this->actingAs($this->createAdmin())
            ->get(route('backend.pages.edit', ['page' => $page, 'lang' => 'fr']))
            ->assertOk()
            ->assertSee('data-translation-copy', false)
            ->assertSee(__('translation.locales.copy_from', ['language' => 'Deutsch']));

        // The German version itself offers nothing — there is no other translation
        $this->actingAs($this->createAdmin())
            ->get(route('backend.pages.edit', ['page' => $page, 'lang' => 'de']))
            ->assertOk()
            ->assertDontSee('data-translation-copy', false);
    }

    public function test_signed_copy_action_copies_and_returns_to_the_editor(): void
    {
        $page = $this->germanPage();
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post($this->copyUrl($page, $admin))
            ->assertRedirect('/backend/pages/'.$page->id.'/edit?lang=fr')
            ->assertSessionHas('success');

        $this->assertSame('de', $page->translationDraftOf('fr'));

        // The language switcher dropdown shows the state
        $this->actingAs($admin)
            ->get(route('backend.pages.edit', ['page' => $page, 'lang' => 'fr']))
            ->assertSee('data-state="draft"', false)
            ->assertSee(__('translation.locales.draft_title', ['language' => 'Deutsch']));
    }

    public function test_copy_action_rejects_tampered_or_foreign_links(): void
    {
        $page = $this->germanPage();
        $admin = $this->createAdmin();
        $other = $this->createAdmin();

        $url = $this->copyUrl($page, $admin);

        $this->actingAs($admin)->post(str_replace('to=fr', 'to=de', $url))->assertForbidden();
        $this->actingAs($other)->post($url)->assertForbidden();
        $this->assertNull($page->translationDraftOf('fr'));
    }

    public function test_copy_link_stops_working_when_the_permission_is_gone(): void
    {
        $page = $this->germanPage();
        $role = Role::create(['name' => 'translator']);
        $role->permissions()->sync(Permission::whereIn('name', ['access-dashboard', 'show-page'])->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        // Without edit-page even a valid URL does not help
        $this->actingAs($user)->post($this->copyUrl($page, $user))->assertForbidden();
        $this->assertNull($page->translationDraftOf('fr'));
    }

    public function test_copied_versions_are_no_source_and_trashed_items_are_skipped(): void
    {
        $page = $this->germanPage();
        $this->locales->register('it', 'Italian', 'Italiano');
        $this->translator->copy($page, 'de', 'fr');

        // Italian: only German is offered, not the copied French version
        $this->actingAs($this->createAdmin())
            ->get(route('backend.pages.edit', ['page' => $page, 'lang' => 'it']))
            ->assertSee(__('translation.locales.copy_from', ['language' => 'Deutsch']))
            ->assertDontSee(__('translation.locales.copy_from', ['language' => 'Français']));

        $trashed = Page::factory()->create(['title' => 'Old', 'slug' => 'old']);
        $trashed->saveTranslations('de', ['title' => 'Alt']);
        $trashed->delete();

        $result = $this->translator->copyLocale('de', 'it');
        $this->assertSame(1, $result['items']);
        $this->assertSame([], $trashed->translationsFor('it'));
        $this->assertSame(1, $this->translator->draftCounts()['it'] ?? 0);
    }

    public function test_whole_language_is_prefilled_without_touching_existing_translations(): void
    {
        $first = $this->germanPage();
        $second = Page::factory()->create(['title' => 'Contact', 'slug' => 'contact']);
        $second->saveTranslations('de', ['title' => 'Kontakt']);
        $second->saveTranslations('fr', ['title' => 'Contact FR']);

        Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'My Site']);
        SettingTranslations::save('site_name', 'de', 'Meine Website', 'My Site');

        $this->actingAs($this->createAdmin())
            ->post(route('backend.multilingual.copy'), ['from' => 'de', 'to' => 'fr'])
            ->assertRedirect(route('backend.multilingual.settings'))
            ->assertSessionHas('success');

        $this->assertSame('Über uns', $first->translationsFor('fr')['title']);
        $this->assertSame('Contact FR', $second->translationsFor('fr')['title']);
        $this->assertSame('de', $first->translationDraftOf('fr'));
        // There was nothing to copy for the second page
        $this->assertNull($second->translationDraftOf('fr'));

        // Setting texts are copied too, but not marked as a draft
        $this->locales->setVisitor('fr');
        $this->assertSame('Meine Website', setting('site_name'));
        $this->assertSame(['de' => 0, 'fr' => 1], ['de' => $this->translator->draftCounts()['de'] ?? 0, 'fr' => $this->translator->draftCounts()['fr'] ?? 0]);
    }

    public function test_prefill_only_between_two_translations(): void
    {
        $admin = $this->createAdmin();
        $base = $this->locales->base();

        $this->actingAs($admin)
            ->post(route('backend.multilingual.copy'), ['from' => $base, 'to' => 'fr'])
            ->assertSessionHasErrors('from');

        $this->actingAs($admin)
            ->post(route('backend.multilingual.copy'), ['from' => 'fr', 'to' => 'fr'])
            ->assertSessionHasErrors('to');
    }

    public function test_settings_page_shows_the_prefill_card(): void
    {
        $this->germanPage();

        $this->actingAs($this->createAdmin())
            ->get(route('backend.multilingual.settings'))
            ->assertOk()
            ->assertSee(__('multilingual::ml.prefill_heading'))
            ->assertDontSee(__('multilingual::ml.prefill_no_source'));
    }

    public function test_command_prefills_a_language(): void
    {
        $page = $this->germanPage();

        $this->artisan('multilingual:copy', ['from' => 'de', 'to' => 'fr'])->assertSuccessful();
        $this->assertSame('Über uns', $page->translationsFor('fr')['title']);

        $this->artisan('multilingual:copy', ['from' => 'de', 'to' => 'xx'])->assertFailed();
    }
}
