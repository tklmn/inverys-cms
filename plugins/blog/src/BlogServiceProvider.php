<?php

namespace Plugins\Blog;

use App\Contracts\ContentTranslator;
use App\Services\Plugins\PluginServiceProvider;
use App\Support\Locales;
use Database\Seeders\Demo\DemoIllustrations;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Plugins\Blog\Http\Controllers\Admin\CategoryController;
use Plugins\Blog\Http\Controllers\Admin\PostController;
use Plugins\Blog\Http\Controllers\Admin\SettingsController;
use Plugins\Blog\Http\Controllers\BlogController;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;
use Plugins\Blog\Search\BlogSearchProvider;
use Plugins\Blog\Search\CategoryAdminSearch;
use Plugins\Blog\Search\PostAdminSearch;
use Plugins\Blog\Services\BlogMediaUsageChecker;
use Plugins\Blog\Sitemap\BlogCategorySitemapProvider;
use Plugins\Blog\Sitemap\BlogSitemapProvider;

class BlogServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        $this->registerAutoloader('Plugins\\Blog');
    }

    public function boot(): void
    {
        $pluginPath = $this->getPluginPath();

        // View namespaces — theme override for frontend
        $this->registerPluginViews('blog', $pluginPath.'/views/frontend');
        $this->registerPluginViews('blog-admin', $pluginPath.'/views/admin');

        // Translations
        $this->app->make('translator')->addNamespace('blog', $pluginPath.'/lang');

        // Frontend routes
        $blogPrefix = $this->settings()->get('blog.url_prefix', 'blog');

        // Addresses of the language counterparts (language menu, hreflang)
        $locales = $this->app->make(Locales::class);
        $locales->pathUsing(Post::class, fn ($post, $slug) => '/'.$blogPrefix.'/'.$slug);
        $locales->pathUsing(BlogCategory::class, fn ($category, $slug) => '/'.$blogPrefix.'/category/'.$slug);
        $this->routes()->web($blogPrefix, function () {
            Route::get('/', [BlogController::class, 'index'])->name('blog.index');
            Route::get('/feed', [BlogController::class, 'feed'])->name('blog.feed');
            Route::get('/category/{category:slug}', [BlogController::class, 'category'])->name('blog.category');
            Route::get('/{slug}', [BlogController::class, 'show'])->name('blog.show');
        });

        // Backend routes
        $this->routes()->backend('blog', function () {
            Route::get('/', [PostController::class, 'index'])->name('backend.blog.index');
            Route::get('/settings', [SettingsController::class, 'edit'])->name('backend.blog.settings');
            Route::put('/settings', [SettingsController::class, 'update'])->name('backend.blog.settings.update');
            Route::get('/create', [PostController::class, 'create'])->name('backend.blog.create');
            Route::post('/', [PostController::class, 'store'])->name('backend.blog.store');
            Route::get('/{post}/edit', [PostController::class, 'edit'])->name('backend.blog.edit');
            Route::put('/{post}', [PostController::class, 'update'])->name('backend.blog.update');
            Route::delete('/{post}', [PostController::class, 'destroy'])->name('backend.blog.destroy');

            Route::get('/categories', [CategoryController::class, 'index'])->name('backend.blog.categories.index');
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('backend.blog.categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->name('backend.blog.categories.store');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('backend.blog.categories.edit');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('backend.blog.categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('backend.blog.categories.destroy');
        });

        // API routes — public read endpoints
        $this->routes()->api('blog', function () {
            Route::get('posts', [Http\Controllers\Api\PostController::class, 'index']);
            Route::get('posts/{id}', [Http\Controllers\Api\PostController::class, 'show'])->where('id', '[a-zA-Z0-9_-]+');
            Route::get('categories', [Http\Controllers\Api\CategoryController::class, 'index']);
            Route::get('categories/{id}', [Http\Controllers\Api\CategoryController::class, 'show'])->where('id', '[a-zA-Z0-9_-]+');
        });

        // API routes — protected write endpoints
        $this->routes()->apiProtected('blog', function () {
            Route::post('posts', [Http\Controllers\Api\PostController::class, 'store'])->middleware('permission:create-post');
            Route::put('posts/{post}', [Http\Controllers\Api\PostController::class, 'update'])->middleware('permission:edit-post');
            Route::delete('posts/{post}', [Http\Controllers\Api\PostController::class, 'destroy'])->middleware('permission:delete-post');
            Route::post('categories', [Http\Controllers\Api\CategoryController::class, 'store'])->middleware('permission:create-blog-category');
            Route::put('categories/{category}', [Http\Controllers\Api\CategoryController::class, 'update'])->middleware('permission:edit-blog-category');
            Route::delete('categories/{category}', [Http\Controllers\Api\CategoryController::class, 'destroy'])->middleware('permission:delete-blog-category');
        });

        // Register extensions via contract
        $this->extensions()->registerSidebarItem([
            'route' => 'backend.blog.index',
            'icon' => 'newspaper',
            'label' => 'blog::blog.posts',
            'active' => 'backend.blog.*',
            'section' => 'content',
            'order' => 30,
        ]);

        $this->extensions()->registerSettingsPrefix('blog.');

        // <link rel="alternate" type="application/rss+xml"> — feed readers find the feed from every page
        $this->extensions()->registerHeadHook('blog::partials.feed-link');

        $this->extensions()->registerDashboardWidget([
            'type' => 'stat',
            'label' => 'blog::blog.dashboard_title',
            'icon' => 'bi-journal-text',
            'icon_bg' => 'bg-purple-500 dark:bg-purple-600',
            'link_class' => 'text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300',
            'route' => 'backend.blog.index',
            'permission' => 'show-post',
            'count' => fn () => Schema::hasTable('posts') ? DB::table('posts')->whereNull('deleted_at')->count() : 0,
        ]);

        $this->extensions()->registerDashboardWidget([
            'type' => 'quick_action',
            'label' => 'translation.dashboard.create_blog_post',
            'icon' => 'bi-journal-plus',
            'icon_class' => 'text-purple-500 dark:text-purple-400',
            'route' => 'backend.blog.create',
            'permission' => 'create-post',
        ]);

        $this->extensions()->registerDashboardWidget([
            'type' => 'widget',
            'label' => 'blog::blog.dashboard_title',
            'icon' => 'bi-journal-richtext',
            'permission' => 'show-post',
            'view' => 'blog-admin::partials.dashboard-widget',
            'data' => fn () => [
                'blogRecentPosts' => Schema::hasTable('posts')
                    ? Post::latest('updated_at')->take(5)->get()
                    : collect(),
            ],
        ]);

        $this->extensions()->registerSearchProvider(new BlogSearchProvider);
        // Post revisions: list, compare, preview and restore as for pages
        $this->extensions()->registerRevisionable('post', [
            'model' => Post::class,
            'permission' => 'edit-post',
            'edit_route' => 'backend.blog.edit',
            'edit_params' => fn (Post $post) => ['post' => $post],
            'labels' => [
                'title' => 'blog::blog.post_title', 'slug' => 'blog::blog.slug', 'excerpt' => 'blog::blog.excerpt',
                'content' => 'blog::blog.content', 'author_id' => 'blog::blog.author', 'featured_image' => 'blog::blog.featured_image',
                'published_at' => 'blog::blog.published_at', 'is_active' => 'blog::blog.is_active',
                'meta_title' => 'blog::blog.meta_title', 'meta_description' => 'blog::blog.meta_description',
            ],
            'preview' => fn (Post $snapshot) => app(BlogController::class)->revisionPreview($snapshot),
            // Restoring must not get round the approval: the state the post
            // ends up in needs the same permission as saving it in the form.
            // A translation revision leaves the status as it is.
            'authorize' => function (Post $post, array $data) {
                $original = ! is_string($data['_locale'] ?? null) || $data['_locale'] === '';

                Post::authorizeStatus(
                    (bool) ($original && array_key_exists('is_active', $data) ? $data['is_active'] : $post->is_active),
                    $original && array_key_exists('published_at', $data) ? $data['published_at'] : $post->published_at,
                );
            },
        ]);
        $this->extensions()->registerAdminSearchProvider(new CategoryAdminSearch);
        $this->extensions()->registerAdminSearchProvider(new PostAdminSearch);
        $this->extensions()->registerMediaUsageChecker(new BlogMediaUsageChecker);
        $this->extensions()->registerSitemapProvider(new BlogSitemapProvider);
        $this->extensions()->registerSitemapProvider(new BlogCategorySitemapProvider);

        $this->extensions()->registerTrashTab([
            'type' => 'post',
            'label' => 'blog::blog.posts',
            'icon' => 'bi-journal-text',
            'model' => Post::class,
            'title_column' => 'title',
            // Restore/permanently delete in the trash
            'permission' => 'delete-post',
        ]);
    }

    public function syncPermissions(): void
    {
        $permNames = [
            'show-post', 'create-post', 'edit-post', 'delete-post',
            'publish-post', 'schedule-post', 'draft-post',
            'show-blog-category', 'create-blog-category', 'edit-blog-category', 'delete-blog-category',
            'blog-settings',
        ];
        foreach ($permNames as $perm) {
            $this->permissions()->createPermission($perm);
        }

        $this->permissions()->assignToRole('admin', $permNames);
        // Editor = content management: posts and categories in full
        // (like pages), the plugin settings stay with the admin
        $this->permissions()->assignToRole('editor', array_values(array_diff($permNames, ['blog-settings'])));
    }

    public function install(): void
    {
        // Create posts table
        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('content')->nullable();
                $table->string('content_type', 20)->default('html');
                $table->json('blocks')->nullable();
                $table->text('excerpt')->nullable();
                $table->string('featured_image')->nullable();
                $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->boolean('is_active')->default(false);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('published_at');
                $table->index('is_active');
            });
        }

        // Create blog_categories table
        if (! Schema::hasTable('blog_categories')) {
            Schema::create('blog_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Create pivot table
        if (! Schema::hasTable('blog_category_post')) {
            Schema::create('blog_category_post', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
                $table->foreignId('blog_category_id')->constrained('blog_categories')->cascadeOnDelete();
                $table->unique(['post_id', 'blog_category_id']);
            });
        }

        // Create permissions and assign to roles
        $this->syncPermissions();

        // Seed default blog settings via contract
        $defaults = [
            'search.include_blog' => ['type' => 'string', 'description' => 'Include blog posts in search results'],
            'search.blog.fields.title' => ['type' => 'string', 'description' => 'Search in blog post titles'],
            'search.blog.fields.content' => ['type' => 'string', 'description' => 'Search in blog post content'],
            'search.blog.fields.excerpt' => ['type' => 'string', 'description' => 'Search in blog post excerpts'],
            'search.blog.fields.meta' => ['type' => 'string', 'description' => 'Search in blog post meta descriptions'],
            'blog.url_prefix' => ['value' => 'blog', 'type' => 'string', 'description' => 'URL prefix for the blog'],
            'blog.posts_per_page' => ['value' => '10', 'type' => 'integer', 'description' => 'Number of posts per page'],
            'blog.columns' => ['value' => '1', 'type' => 'integer', 'description' => 'Number of columns in blog listing'],
            'blog.sort_by' => ['value' => 'published_at', 'type' => 'string', 'description' => 'Sort posts by field'],
            'blog.sort_direction' => ['value' => 'desc', 'type' => 'string', 'description' => 'Sort direction (asc/desc)'],
            'blog.show_pagination' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show pagination on blog listing'],
            'blog.show_author' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show author on blog posts'],
            'blog.show_date' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show date on blog posts'],
            'blog.show_categories' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show categories on blog posts'],
            'blog.show_excerpt' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show excerpt on blog listing'],
            'blog.show_featured_image' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show featured image on blog posts'],
            'blog.show_read_more' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show read more link on blog listing'],
            'blog.show_breadcrumbs' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show breadcrumbs on single post'],
            'blog.show_prev_next' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show previous/next navigation on single post'],
            'blog.show_sidebar' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show sidebar with categories on listing'],
            'blog.show_rss_link' => ['value' => '1', 'type' => 'boolean', 'description' => 'Show RSS feed link in sidebar'],
        ];

        $this->seedSettings($defaults);

        // Create a "Blog" page in the page tree for navigation
        $urlPrefix = 'blog';
        $maxOrder = $this->pages()->maxOrder();
        $this->pages()->create([
            'slug' => $urlPrefix,
            'title' => 'Blog',
            'nav_title' => 'Blog',
            'content_type' => 'html',
            'content' => '',
            // Path instead of full address: survives domain changes, gets the language prefix
            'external_url' => '/'.$urlPrefix,
            'is_active' => true,
            'level' => 0,
            'order' => $maxOrder + 1,
            'hide_in_menu' => false,
            'include_in_search' => false,
        ]);

        // Seed demo content when running via CLI (artisan), not via web installer
        if (app()->runningInConsole()) {
            $this->seedDemoContent();
        }
    }

    public function seedDemoContent(): void
    {
        $author = $this->users()->first();

        // Generate blog demo SVGs on disk and create Media records
        $this->seedBlogMedia($author);

        // Categories
        $categories = [];
        foreach ([
            ['name' => 'Tutorials', 'slug' => 'tutorials', 'description' => 'Step-by-step guides and how-tos'],
            ['name' => 'News', 'slug' => 'news', 'description' => 'Latest updates and announcements'],
            ['name' => 'Tips & Tricks', 'slug' => 'tips-tricks', 'description' => 'Quick tips for getting the most out of the CMS'],
        ] as $cat) {
            $categories[$cat['slug']] = BlogCategory::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }

        // Posts
        $posts = $this->demoPosts();

        foreach ($posts as $postData) {
            $catSlugs = $postData['categories'];
            unset($postData['categories']);

            $post = Post::firstOrCreate(
                ['slug' => $postData['slug']],
                array_merge($postData, [
                    'content_type' => 'html',
                    'is_active' => true,
                    'author_id' => $author?->id,
                ])
            );

            $categoryIds = collect($catSlugs)
                ->map(fn ($slug) => $categories[$slug]->id ?? null)
                ->filter()
                ->toArray();

            $post->categories()->syncWithoutDetaching($categoryIds);
        }
    }

    /**
     * Bring the demo posts of an existing installation up to date (cms:demo-refresh).
     * Only posts that still exist are refreshed; each gets a revision first, so
     * own changes can be restored. Translations of the replaced texts are removed —
     * language packs lay their current version on top afterwards.
     *
     * @return int number of refreshed posts
     */
    public function refreshDemoContent(): int
    {
        $translator = app(ContentTranslator::class);
        $fields = ['title', 'excerpt', 'content', 'meta_description'];
        $refreshed = 0;

        foreach ($this->demoPosts() as $definition) {
            $post = Post::where('slug', $definition['slug'])->first();
            if (! $post) {
                continue;
            }

            $post->createRevision('Demo content refresh');
            $post->forceFill(array_intersect_key($definition, array_flip($fields)))->saveQuietly();

            foreach ($translator->locales($post) as $locale) {
                $translator->save($post, $locale, array_fill_keys($fields, null));
            }
            $refreshed++;
        }

        return $refreshed;
    }

    /** The demo posts (installer and cms:demo-refresh) */
    private function demoPosts(): array
    {
        return [
            [
                'title' => 'Getting Started with Inverys CMS',
                'slug' => 'getting-started-with-inverys-cms',
                'excerpt' => 'Learn how to set up your Inverys CMS installation and create your first page using the intuitive page builder.',
                'content' => '<h2>Welcome to Inverys CMS</h2><p>Inverys CMS is a modern, Laravel-based content management system built with developers and content editors in mind. This guide will walk you through the basics of getting started.</p><h3>Installation</h3><p>Clone the repository, run <code>composer install</code> and <code>npm install &amp;&amp; npm run build</code>, then open the site in your browser. The web installer checks the requirements, connects the database and asks for the site name, the language and optional demo content. You create the administrator account yourself — there are no preset credentials. On shared hosting, upload the release package instead; it already contains everything that needs a build.</p><h3>Creating Your First Page</h3><p>Navigate to the backend, click on Pages, and then "Add New Page". Give the page a title and choose how it starts: blank, as a text page or as a landing page. It opens in the full-screen editor with a live preview in your theme and 23 block types, including columns, tabs, accordions, cards, and more.</p><h3>Choosing a Theme</h3><p>Head to Extensions → Themes in the backend to browse and activate available themes. Each theme comes with its own settings in the customizer and block-based content regions such as the footer.</p><h3>Next Steps</h3><p>Explore the <a href="/features">features page</a> for a full overview, or check out the <a href="/faq">FAQ</a> for answers to common questions.</p>',
                'meta_description' => 'A beginner-friendly guide to setting up and using Inverys CMS for your website.',
                'featured_image' => 'media/demo/blog-getting-started.svg',
                'categories' => ['tutorials'],
                'published_at' => now()->subDays(28),
            ],
            [
                'title' => 'Mastering the Page Builder',
                'slug' => 'mastering-the-page-builder',
                'excerpt' => 'Dive deep into the drag-and-drop page builder and learn how to create stunning layouts with columns, tabs, and accordion blocks.',
                'content' => '<h2>The Page Builder</h2><p>The Inverys CMS page builder offers 23 block types across three categories: Layout, Content, and Interactive — the form and gallery plugins add two more. Pick blocks from the "Add block" dialog, arrange them by drag and drop and adjust them in the sidebar — no coding required.</p><h3>Layout Blocks</h3><p>Use <strong>containers</strong>, <strong>sections</strong> (with backgrounds and spacing chosen from lists), <strong>columns</strong> (up to six, with equal or unequal widths), <strong>spacers</strong> and <strong>dividers</strong> to structure your page. A <strong>hero</strong> opens a page with headline, text and buttons, and sections can have gradient backgrounds.</p><h3>Content Blocks</h3><p>Add <strong>headings</strong> (H1-H6 with alignment), <strong>rich text</strong> (powered by TinyMCE), <strong>images</strong> (with media library browser), <strong>videos</strong> (YouTube, Vimeo or a video file), <strong>quotes</strong>, <strong>code snippets</strong>, <strong>lists</strong>, <strong>key figures</strong>, <strong>pricing tables</strong> and <strong>logo strips</strong>.</p><h3>Interactive Blocks</h3><p>Create engaging content with <strong>accordion</strong> sections (expandable panels), <strong>tabbed</strong> interfaces, <strong>buttons</strong> (primary, secondary, outline, ghost and more), <strong>link groups</strong>, <strong>alerts</strong> (info/success/warning/danger), and <strong>cards</strong> with optional images.</p><h3>Tips</h3><ul><li>Click a block in the preview to select it — a second click edits its text right on the page</li><li>Auto-save keeps your work safe — every change is saved, and <kbd>Ctrl</kbd>+<kbd>Z</kbd> undoes it</li><li>The toolbar at the selected block moves, duplicates or deletes it; press <kbd>?</kbd> for all keyboard shortcuts</li></ul>',
                'meta_description' => 'Learn how to use the 23 block types of the Inverys CMS page builder.',
                'featured_image' => 'media/demo/blog-page-builder.svg',
                'categories' => ['tutorials'],
                'published_at' => now()->subDays(21),
            ],
            [
                'title' => 'Introducing the Theme System',
                'slug' => 'introducing-the-theme-system',
                'excerpt' => 'Discover how to switch themes, customize settings, and build your own theme for Inverys CMS.',
                'content' => '<h2>Themes in Inverys CMS</h2><p>The theme system allows you to completely change the look and feel of your site with a single click. Each theme is a self-contained directory with its own Blade views, settings, and content regions.</p><h3>Two Bundled Themes</h3><p>Inverys ships with a <strong>default</strong> theme (light and dark mode, with colors, font, corners, header and footer set in the customizer) and a <strong>modern</strong> theme (magazine-style with gradient accents and centered navigation). Switch between them in Extensions → Themes.</p><h3>Theme Customization</h3><p>Every theme declares its settings in <code>theme.json</code>. Supported field types include color pickers, image uploads, text inputs, textareas, select dropdowns, and boolean toggles. Group settings into logical sections for a clean admin UI; until a setting is saved, its default from <code>theme.json</code> applies.</p><h3>Content Regions</h3><p>Themes can declare block-based content regions (like the footer) that reuse the same page builder. This means your footer content is just as flexible as your page content — add columns, links, text, images, anything.</p><h3>Build Your Own</h3><p>Creating a theme is straightforward: create a directory in <code>themes/</code>, add a <code>theme.json</code> manifest, and write your Blade views. Static files go into its <code>assets/</code> folder. See the <a href="/documentation">theme development guide</a> for the full walkthrough.</p>',
                'meta_description' => 'An overview of the Inverys CMS theme system with customization and development tips.',
                'featured_image' => 'media/demo/blog-themes.svg',
                'categories' => ['news'],
                'published_at' => now()->subDays(14),
            ],
            [
                'title' => '5 Tips for Managing Content Efficiently',
                'slug' => '5-tips-for-managing-content-efficiently',
                'excerpt' => 'Quick productivity tips for content editors using Inverys CMS, from the tree view to SEO fields.',
                'content' => '<h2>Work Smarter with Inverys CMS</h2><p>Whether you manage a handful of pages or hundreds of posts, these tips will help you work faster and stay organized.</p><ol><li><strong>Use the page tree view</strong> — Drag and drop pages in the tree to reorganize your site hierarchy instantly. Parent-child relationships update automatically.</li><li><strong>Auto-save is your friend</strong> — The page editor saves every change on its own and shows the state in the top bar. Each editing session is kept as a revision, so earlier versions can be compared, previewed and restored.</li><li><strong>Categories for organization</strong> — Group pages with page categories and posts with blog categories to keep content organized. Visitors can browse the blog by category.</li><li><strong>Leverage templates</strong> — Choose between container and full-width templates. Full-width is great for landing pages with hero sections; container keeps content readable.</li><li><strong>SEO fields on every page</strong> — Every page has dedicated SEO fields: meta title, description, robots directives, canonical URL, and Open Graph tags. Fill them in to improve search visibility.</li></ol>',
                'meta_description' => 'Five practical tips for working efficiently with Inverys CMS.',
                'featured_image' => 'media/demo/blog-tips.svg',
                'categories' => ['tips-tricks'],
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Building Plugins for Inverys CMS',
                'slug' => 'building-plugins-for-inverys-cms',
                'excerpt' => 'A developer guide to creating custom plugins that extend Inverys CMS with new features and functionality.',
                'content' => '<h2>Plugin Development</h2><p>Inverys CMS has a modular plugin architecture that lets you add new features without modifying core code. The bundled blog plugin is a full reference implementation.</p><h3>Plugin Structure</h3><p>Each plugin lives in its own folder in the <code>plugins/</code> directory (or is uploaded as a ZIP file under Extensions → Plugins) and typically contains:</p><ul><li><code>plugin.json</code> — manifest with slug, name, version, description, and provider class</li><li><code>src/</code> — PHP classes (service provider, models, controllers)</li><li><code>views/</code> — Blade templates for frontend and admin</li><li><code>lang/</code> — translation files (namespaced)</li></ul><h3>Lifecycle Hooks</h3><p>Plugins extend <code>PluginServiceProvider</code>, which provides these methods:</p><ul><li><code>register()</code> — set up autoloading and bindings</li><li><code>boot()</code> — register routes, views, translations</li><li><code>install()</code> — create tables, permissions, seed data (runs on first enable)</li><li><code>upgrade()</code> — migrate data once when a new version is loaded</li><li><code>uninstall()</code> — clean up when the plugin is deleted</li></ul><h3>Settings Route</h3><p>Add <code>"settings_route": "your.settings.route"</code> to <code>plugin.json</code> and a Settings button will appear on the plugin management page automatically. With <code>"search_keywords"</code>, the settings page is also found by the quick search.</p>',
                'meta_description' => 'Learn how to develop custom plugins for Inverys CMS.',
                'featured_image' => 'media/demo/blog-plugins.svg',
                'categories' => ['tutorials'],
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'API Access and Integration Guide',
                'slug' => 'api-access-and-integration-guide',
                'excerpt' => 'Learn how to use the Inverys CMS REST API to manage content programmatically and integrate with external services.',
                'content' => '<h2>The REST API</h2><p>Inverys CMS includes a full RESTful API under <code>/api/v1/</code> with token-based authentication and per-route permission checks.</p><h3>Authentication</h3><p>Create an API token in the backend under System → Settings → API Tokens — full, read-write or read-only, with an optional expiry — or request one with <code>POST /auth/login</code>. Include it in every request via the <code>Authorization: Bearer &lt;token&gt;</code> header. Protected endpoints also require the <code>api-access</code> permission; reading published blog posts and categories needs no token.</p><h3>Available Endpoints</h3><table><thead><tr><th>Area</th><th>Endpoints</th></tr></thead><tbody><tr><td>Auth</td><td>POST /auth/login, POST /auth/logout, GET /auth/me</td></tr><tr><td>Pages</td><td>GET/POST /pages, GET/PUT/DELETE /pages/{id}</td></tr><tr><td>Media</td><td>GET/POST /media, GET/DELETE /media/{id}</td></tr><tr><td>Users</td><td>GET /users, GET /users/{id}</td></tr><tr><td>Settings</td><td>GET /settings, PUT /settings/{key}</td></tr><tr><td>Block types</td><td>GET /blocks/types</td></tr><tr><td>Themes</td><td>GET /themes, PUT /themes/active</td></tr><tr><td>Plugins</td><td>GET /plugins, PUT /plugins/{slug}</td></tr><tr><td>Blog</td><td>GET/POST /blog/posts, GET/PUT/DELETE /blog/posts/{id}, the same for /blog/categories</td></tr></tbody></table><h3>Rate Limiting</h3><p>API requests are rate-limited per minute — 60 by default, adjustable under Settings → Security. Every response carries the <code>X-RateLimit-Limit</code> and <code>X-RateLimit-Remaining</code> headers; above the limit the API answers with status 429 and a <code>Retry-After</code> header.</p>',
                'meta_description' => 'A complete guide to the Inverys CMS REST API with authentication and endpoint reference.',
                'featured_image' => 'media/demo/blog-api.svg',
                'categories' => ['tutorials'],
                'published_at' => now()->subDays(1),
            ],
        ];
    }

    private function seedBlogMedia(mixed $author): void
    {
        $images = [
            ['file' => 'blog-getting-started.svg', 'name' => 'Getting Started', 'alt' => 'Getting started guide illustration', 'color' => '#3b82f6'],
            ['file' => 'blog-page-builder.svg', 'name' => 'Page Builder', 'alt' => 'Visual page builder illustration', 'color' => '#8b5cf6'],
            ['file' => 'blog-themes.svg', 'name' => 'Theme System', 'alt' => 'Theme customization illustration', 'color' => '#ec4899'],
            ['file' => 'blog-tips.svg', 'name' => 'Tips and Tricks', 'alt' => 'Productivity tips illustration', 'color' => '#10b981'],
            ['file' => 'blog-plugins.svg', 'name' => 'Plugin Development', 'alt' => 'Plugin architecture illustration', 'color' => '#f59e0b'],
            ['file' => 'blog-api.svg', 'name' => 'API Integration', 'alt' => 'REST API integration illustration', 'color' => '#06b6d4'],
        ];

        foreach ($images as $img) {
            $path = 'media/demo/'.$img['file'];
            // The same illustrations as the demo pages (no text, light and dark);
            // the labelled placeholder ("Demo Image") only if they do not exist
            $svg = class_exists(DemoIllustrations::class) && DemoIllustrations::has($img['file'])
                ? DemoIllustrations::svg($img['file'])
                : $this->generateDemoSvg($img['name'], $img['color']);

            $this->media()->storeAndRecord($path, $svg, [
                'name' => $img['name'],
                'file_name' => $img['file'],
                'thumbnail_path' => $path,
                'mime_type' => 'image/svg+xml',
                'alt_text' => $img['alt'],
                'description' => $img['alt'],
                'user_id' => $author?->id,
            ]);
        }
    }

    private function generateDemoSvg(string $title, string $color): string
    {
        $escaped = htmlspecialchars($title, ENT_XML1);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 400">
          <defs>
            <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" style="stop-color:{$color};stop-opacity:.15"/>
              <stop offset="100%" style="stop-color:{$color};stop-opacity:.05"/>
            </linearGradient>
          </defs>
          <rect width="800" height="400" fill="url(#bg)" rx="12"/>
          <rect x="50" y="50" width="700" height="300" fill="none" stroke="{$color}" stroke-opacity=".2" stroke-width="2" rx="8" stroke-dasharray="8 4"/>
          <text x="400" y="190" text-anchor="middle" font-family="system-ui,sans-serif" font-size="28" font-weight="600" fill="{$color}">{$escaped}</text>
          <text x="400" y="230" text-anchor="middle" font-family="system-ui,sans-serif" font-size="16" fill="{$color}" opacity=".6">Demo Image</text>
        </svg>
        SVG;
    }
}
