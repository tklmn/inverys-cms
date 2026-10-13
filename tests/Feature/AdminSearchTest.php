<?php

namespace Tests\Feature;

use App\Contracts\AdminSearchProvider;
use App\Contracts\ExtensionRegistrar;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quick search in the backend (Ctrl/⌘ + K): results from core and plugins,
 * grouped and with the user's permissions.
 */
class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    private function searchGroups(User $user, string $query): array
    {
        return collect($this->actingAs($user)->getJson(route('backend.search', ['q' => $query]))->assertOk()->json('groups'))
            ->keyBy('key')
            ->all();
    }

    public function test_guests_and_users_without_backend_access_get_nothing(): void
    {
        $this->getJson(route('backend.search', ['q' => 'about']))->assertUnauthorized();
        $this->actingAs($this->createUser())->getJson(route('backend.search', ['q' => 'about']))->assertForbidden();
    }

    public function test_finds_pages_including_inactive_ones_and_links_to_the_editor(): void
    {
        $page = Page::factory()->create(['title' => 'Our team', 'slug' => 'team', 'is_active' => false]);

        $groups = $this->searchGroups($this->createAdmin(), 'team');

        $this->assertSame(route('backend.pages.edit', $page), $groups['pages']['items'][0]['url']);
        $this->assertSame('Our team', $groups['pages']['items'][0]['title']);
        $this->assertStringContainsString('/team', $groups['pages']['items'][0]['context']);
    }

    public function test_all_words_must_match_and_wildcards_are_literal(): void
    {
        Page::factory()->create(['title' => 'Prices and packages', 'slug' => 'prices']);
        Page::factory()->create(['title' => 'Prices', 'slug' => 'prices-old']);

        $groups = $this->searchGroups($this->createAdmin(), 'prices packages');
        $this->assertCount(1, $groups['pages']['items']);

        $this->assertArrayNotHasKey('pages', $this->searchGroups($this->createAdmin(), '%%'));
    }

    public function test_short_queries_return_no_groups(): void
    {
        Page::factory()->create(['title' => 'A-Team']);

        $this->assertSame([], $this->searchGroups($this->createAdmin(), 'a'));
    }

    public function test_results_follow_the_permissions_of_the_user(): void
    {
        $admin = $this->createAdmin(['name' => 'Zora Example', 'email' => 'zora@example.com']);
        $editor = $this->createEditor();

        $this->assertArrayHasKey('users', $this->searchGroups($admin, 'zora'));
        // Editors do not manage users
        $this->assertArrayNotHasKey('users', $this->searchGroups($editor, 'zora'));
    }

    public function test_settings_pages_are_found_by_their_keywords(): void
    {
        $admin = $this->createAdmin();

        $urls = array_column($this->searchGroups($admin, 'sitemap')['settings']['items'], 'url');
        $this->assertContains(route('backend.settings.seo'), $urls);

        $urls = array_column($this->searchGroups($admin, 'two-factor')['settings']['items'] ?? [], 'url');
        $this->assertContains(route('backend.settings.security'), $urls);

        // Without the permission for the security settings the page is missing
        $urls = array_column($this->searchGroups($this->createEditor(), 'two-factor')['settings']['items'] ?? [], 'url');
        $this->assertNotContains(route('backend.settings.security'), $urls);
    }

    public function test_single_general_settings_are_found(): void
    {
        Setting::updateOrCreate(['key' => 'timezone'], ['value' => 'Europe/Berlin', 'type' => 'string']);

        $urls = array_column($this->searchGroups($this->createAdmin(), 'time zone')['settings']['items'] ?? [], 'title');

        $this->assertContains(__('translation.settings.general_keys.timezone'), $urls);
    }

    public function test_plugins_can_add_a_group_and_a_failing_provider_does_not_break_the_search(): void
    {
        $extensions = app(ExtensionRegistrar::class);
        $extensions->registerAdminSearchProvider(new class implements AdminSearchProvider
        {
            public function key(): string
            {
                return 'projects';
            }

            public function label(): string
            {
                return 'Projects';
            }

            public function icon(): string
            {
                return 'folder';
            }

            public function search(string $query, User $user, int $limit): iterable
            {
                yield ['title' => 'Project '.$query, 'url' => 'https://example.org/p/1', 'context' => 'GitHub'];
            }
        });
        $extensions->registerAdminSearchProvider(new class implements AdminSearchProvider
        {
            public function key(): string
            {
                return 'broken';
            }

            public function label(): string
            {
                return 'Broken';
            }

            public function icon(): string
            {
                return 'bug';
            }

            public function search(string $query, User $user, int $limit): iterable
            {
                throw new \RuntimeException('broken');
            }
        });

        $groups = $this->searchGroups($this->createAdmin(), 'inverys');

        $this->assertSame('Projects', $groups['projects']['label']);
        $this->assertSame('folder', $groups['projects']['icon']);
        $this->assertSame('Project inverys', $groups['projects']['items'][0]['title']);
        $this->assertArrayNotHasKey('broken', $groups);
    }

    public function test_settings_index_has_no_list_of_other_pages_anymore(): void
    {
        $this->actingAs($this->createAdmin())
            ->get(route('backend.settings.index'))
            ->assertOk()
            ->assertDontSee('Configured elsewhere');
    }
}
