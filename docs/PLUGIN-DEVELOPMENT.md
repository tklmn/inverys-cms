# 🔌 Plugin Development Guide

> Build, distribute, and install plugins for Inverys CMS.

This guide is a comprehensive, step-by-step reference for building plugins for Inverys CMS. It covers the full plugin architecture -- from directory layout and manifest format to service providers, routes, views, API endpoints, permissions, settings, search integration, distribution via ZIP and Composer, and more.

---

## 📑 Table of Contents

- [1. Overview](#1--overview)
- [2. Directory Structure](#2--directory-structure)
- [3. Plugin Manifest (plugin.json)](#3--plugin-manifest-pluginjson)
- [4. composer.json -- Composer Distribution](#4--composerjson----composer-distribution)
- [5. Service Provider](#5--service-provider)
- [6. Contract Accessors / CMS SDK](#6--contract-accessors--cms-sdk)
- [7. PSR-4 Autoloading](#7--psr-4-autoloading)
- [8. Routes](#8--routes)
- [9. Views and Theme Overrides](#9--views-and-theme-overrides)
- [10. Translations](#10--translations)
- [11. Database Tables](#11--database-tables)
- [12. Permissions](#12--permissions)
- [13. Settings](#13--settings)
- [14. Sidebar Integration](#14--sidebar-integration)
- [15. Search Provider](#15--search-provider)
- [16. Trash Tab Integration](#16--trash-tab-integration)
- [16a. Revisions](#16a--revisions)
- [16b. Frontend Hooks (Body & Head)](#16b--frontend-hooks-body--head)
- [17. API Routes](#17--api-routes)
- [18. Example: Minimal Plugin](#18--example-minimal-plugin)
- [19. Reference: Blog Plugin](#19--reference-blog-plugin)
- [20. Distribution](#20--distribution)

---

## 1. 🔍 Overview

Inverys CMS uses a modular plugin architecture. Plugins live in the `plugins/` directory at the project root. Each plugin is a self-contained directory with its own manifest, service provider, models, controllers, views, translations, and assets.

### How Discovery Works

The `PluginManager` scans every subdirectory of `plugins/` for a `plugin.json` file. If the file exists and contains a valid `slug` field, the directory is registered as a known plugin. This happens automatically -- you do not need to register your plugin anywhere else.

```
PluginManager::discover()
  -> scans plugins/*/plugin.json
  -> scans vendor packages with "type": "inverys-plugin" (via installed.json)
  -> parses each manifest
  -> stores slug => manifest data (including path) in memory
```

### Enable / Disable Lifecycle

Plugins are enabled and disabled through the admin panel at **Extensions > Plugins**.

**Enabling a plugin:**

1. `PluginManager::enable()` creates or updates the plugin's database record, setting `is_enabled = true`.
2. It calls `loadPlugin()`, which loads the provider class (registering a PSR-4 autoloader for the provider's namespace if the class is not autoloadable yet), instantiates it, and calls `register()` then `boot()`. A provider that throws is logged and the plugin is disabled again.
3. If this is the first time the plugin has been enabled (no `installed_at` timestamp), the manager calls `install()` after `boot()`, then `syncPermissions()` (on every enable).

**Disabling a plugin:**

1. `PluginManager::disable()` sets `is_enabled = false` and clears the `enabled_at` timestamp.
2. On the next request, the plugin's service provider is not loaded, so its routes, views, and sidebar items are not registered.
3. `uninstall()` is **not** called on disable -- it is only invoked on explicit uninstallation.

**On every request (for enabled plugins):**

1. `PluginManager::boot()` queries all enabled plugins ordered by `order`.
2. For each enabled plugin, it calls `loadPlugin()`, which instantiates the provider and calls `register()` then `boot()`.

### Key Classes

| Class | Path | Role |
|:------|:-----|:-----|
| `PluginManager` | `app/Services/PluginManager.php` | Discovers, enables, disables, and loads plugins |
| `PluginServiceProvider` | `app/Services/Plugins/PluginServiceProvider.php` | Abstract base class all plugins extend |
| `Plugin` | `app/Models/Plugin.php` | Eloquent model for the `plugins` database table |
| `CmsManager` | `app/Services/CmsManager.php` | Facade accessed via `cms()` global helper |

---

## 2. 📂 Directory Structure

Every plugin lives in `plugins/{slug}/` and follows this layout:

```
plugins/
  my-plugin/
    plugin.json                  # Required: manifest file
    composer.json                # Optional: for Composer distribution
    src/
      MyPluginServiceProvider.php       # Required: service provider
      Http/
        Controllers/
          MyController.php              # Frontend controllers
          Admin/
            AdminController.php         # Backend controllers
          Api/
            ApiController.php           # API controllers (optional)
        Resources/
          MyResource.php                # API resources (optional)
      Models/
        MyModel.php                     # Eloquent models
      Search/
        MySearchProvider.php            # Search integration (optional)
    views/
      frontend/
        index.blade.php                 # Frontend templates
        show.blade.php
      admin/
        index.blade.php                 # Backend templates
        create.blade.php
        edit.blade.php
    lang/
      en/
        my-plugin.php                   # English translations
      de/
        my-plugin.php                   # German translations
    assets/                             # Optional: CSS, JS, images
```

> **📌 Note:** The `slug` in your `plugin.json` must match the directory name. For example, a plugin at `plugins/hello-world/` must have `"slug": "hello-world"` in its manifest.

---

## 3. 📋 Plugin Manifest (`plugin.json`)

Every plugin requires a `plugin.json` file in its root directory. This file tells the PluginManager how to identify and load the plugin.

### Full Example

```json
{
    "slug": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "description": "A short description of what the plugin does.",
    "author": "Your Name",
    "provider": "Plugins\\MyPlugin\\MyPluginServiceProvider",
    "settings_route": "backend.my-plugin.settings"
}
```

### Field Reference

| Field | Type | Required | Description |
|:------|:-----|:--------:|:------------|
| `slug` | `string` | Yes | Unique identifier. Must match the directory name under `plugins/`. Lowercase with hyphens (e.g. `my-plugin`). |
| `name` | `string` | Yes | Human-readable display name shown in the admin panel. |
| `version` | `string` | Yes | Semantic version (e.g. `1.0.0`). |
| `description` | `string` | Yes | Brief description shown in the plugin management page. |
| `author` | `string` | Yes | Plugin author name. |
| `provider` | `string` | Yes | Fully qualified class name of the service provider. Uses double-backslash escaping in JSON (e.g. `Plugins\\MyPlugin\\MyPluginServiceProvider`). |
| `settings_route` | `string` | No | Named route for the plugin's settings page. When set, a "Settings" link appears next to the plugin in the admin panel, and the quick search (Ctrl K) finds the page by the plugin name. |
| `search_keywords` | `string` | No | Topics of the settings page for the quick search, separated by commas — plain text or a translation key such as `my-plugin::my-plugin.search_keywords` (then translate it per language). Example: `"SMTP, sender, test mail"`. |
| `repository` | `string` | No | Update source: a GitHub repository as `owner/repo`. When it has a newer release, the plugin list shows an **Update** button (see [Updates](#updates)). |
| `is_system` | `boolean` | No | Mandatory plugin: the web installer enables it (and aborts if that fails), and it cannot be deleted from the plugin list. Meant for plugins bundled with the CMS. |
| `type`, `locale`, `native` | `string` | No | Language packs only: `"type": "language"`, the locale code (e.g. `de`) and the language's own name (e.g. `Deutsch`). The plugin list can be filtered to language packs; see [`plugins/language-de`](../plugins/language-de/README.md). |
| `license` | `string` | No | SPDX license identifier, used in the software bill of materials (SBOM). |

The CMS itself enforces only `slug` (discovery) and `provider` (loading, ZIP upload). Fill in the other fields anyway: `version` drives `upgrade()`, updates and the cache busting of `plugin_asset()`.

> **💡 Tip:** The `PluginManager` injects a `path` key into the manifest at runtime, containing the absolute filesystem path to the plugin directory. Access it via `$this->manifest['path']` or `$this->getPluginPath()`.

---

## 4. 📦 `composer.json` -- Composer Distribution

If you want to distribute your plugin as a Composer package, add a `composer.json` to the plugin root. This enables installation via `composer require` and automatic discovery by the CMS.

### Full Example

```json
{
    "name": "inverys/announcement-bar",
    "description": "Displays a configurable announcement bar at the top of every frontend page.",
    "type": "inverys-plugin",
    "version": "1.0.0",
    "license": "MIT",
    "authors": [
        { "name": "Third-Party Dev", "email": "dev@example.com" }
    ],
    "extra": {
        "inverys": {
            "slug": "announcement-bar"
        }
    },
    "autoload": {
        "psr-4": {
            "Plugins\\AnnouncementBar\\": "src/"
        }
    }
}
```

### Required Fields

| Field | Value | Purpose |
|:------|:------|:--------|
| `type` | `"inverys-plugin"` | Tells the CMS installer this is a plugin package. The CMS scans `vendor/*/composer.json` for this type. |
| `extra.inverys.slug` | `"your-slug"` | The plugin slug. Must match the `slug` in `plugin.json`. Used by the CMS to locate the plugin. |
| `autoload.psr-4` | `{"Plugins\\YourPlugin\\": "src/"}` | PSR-4 namespace mapping. Composer handles autoloading for vendor-installed packages. |

### How Composer Discovery Works

1. A developer runs `composer require vendor/your-plugin`.
2. Composer installs the package into `vendor/vendor/your-plugin/`.
3. On the next request, the `PluginManager` reads Composer's `vendor/composer/installed.json`.
4. It finds all packages with `"type": "inverys-plugin"`.
5. It reads `extra.inverys.slug` to get the plugin slug and resolves the install path.
6. The plugin's `plugin.json` is loaded from the resolved path.
7. The plugin appears in **Extensions > Plugins** and can be enabled like any local plugin.

> **📌 Note:** Composer-installed plugins do not need the `registerAutoloader()` call in `register()` since Composer handles class autoloading via PSR-4. The manual autoloader is only needed for ZIP-uploaded plugins in the `plugins/` directory.

---

## 5. ⚡ Service Provider

Every plugin must have a service provider class that extends `App\Services\Plugins\PluginServiceProvider`. This is the single entry point for all plugin logic.

### 5.1 Base Class

The `PluginServiceProvider` abstract base class provides:

| Property / Method | Description |
|:------------------|:------------|
| `$this->app` | The Laravel application instance (`Illuminate\Contracts\Foundation\Application`). |
| `$this->plugin` | The `Plugin` Eloquent model for this plugin (database record). |
| `$this->manifest` | The parsed `plugin.json` data as an array. Includes the runtime `path` key. |
| `$this->getPluginPath()` | Returns the absolute filesystem path to the plugin directory. |
| `$this->registerAutoloader($namespace)` | Registers a PSR-4 autoloader for the given namespace, mapping to `src/` in the plugin directory. |
| `$this->registerPluginViews($namespace, $viewsPath)` | Registers a view namespace with theme-override support (see [section 9](#9--views-and-theme-overrides)). |
| `$this->extendPluginViews($namespace, $viewsPath)` | Replaces some views of another namespace with this plugin's views; a theme override still wins. |
| `$this->seedSettings($defaults)` | Creates settings that do not exist yet: `['key' => ['value' => …, 'type' => …, 'description' => …]]` (defaults: `'on'`, `'string'`, `''`). |
| `$this->registerBlockType($attributes)` / `$this->unregisterBlockType($slug)` | Adds or removes a page-builder block type owned by the plugin (see [5.5](#55-install----schema-settings-seeding)). |
| `$this->getSetting($key, $default)` | Reads a value from the plugin's `settings` JSON column on the `Plugin` model. |
| `$this->cms()` | The `CmsManager`, same as the global `cms()` helper. |

### 5.2 Lifecycle Methods

| Method | When It Runs | Purpose |
|:-------|:-------------|:--------|
| `register()` | Every request when the plugin is enabled | Set up PSR-4 autoloading, bind interfaces to implementations |
| `boot()` | Every request after `register()` | Register routes, views, translations, sidebar items, search providers, trash tabs |
| `install()` | Once, on the very first enable (`installed_at` is null) | Create database tables, seed default data and settings |
| `syncPermissions()` | On **every** enable (after `install()`) | Register/refresh the plugin's permissions and assign them to roles — idempotent, so permissions added in a later version are picked up on re-enable |
| `upgrade($from, $to)` | Once, when a new version of the plugin is loaded for the first time (update, upload, `git pull`) | Schema changes and data migrations between versions — write them idempotently (`Schema::hasColumn` …) |
| `uninstall()` | On explicit uninstallation only (not on disable) | Drop tables, remove permissions, clean up data |

The `PluginManager` calls these methods in strict order:

```
register()  -->  boot()  -->  install() (first enable only)  -->  syncPermissions() (every enable)
                         -->  upgrade() + syncPermissions() (version on disk differs from the stored one)
```

### Static Files (`assets/`)

Files in the plugin's `assets/` directory are served at `/ext-assets/plugin/{slug}/{path}` (route `extension.asset`; types css, js, mjs, woff2, woff, svg, png, jpg, jpeg, webp, avif, gif, ico; no hidden files or folders, nothing outside `assets/`, not even through a symlink). Link them with `plugin_asset(string $slug, string $path)` — the URL carries `?v={version}` from `plugin.json`, so browsers cache the file for a year (`immutable`) and fetch it again after an update; without `?v` the cache time is one hour. SVG files are sent with their own sandboxing CSP.

```blade
@once
    <link rel="stylesheet" href="{{ plugin_asset('my-plugin', 'css/blocks.css') }}">
@endonce
```

`@once` counts per place in a template: put it in one shared partial that every block includes, not in each block view.

### Updates

The version in `plugin.json` is compared with the version stored for the plugin. When they differ, the next request runs `upgrade($from, $to)` once, re-syncs the permissions and stores the new version. If `upgrade()` throws, the plugin is disabled; after an update from the plugin list the previous version is put back automatically.

```php
public function upgrade(string $fromVersion, string $toVersion): void
{
    if (version_compare($fromVersion, '1.2.0', '<') && ! Schema::hasColumn('my_items', 'sort')) {
        Schema::table('my_items', fn (Blueprint $table) => $table->unsignedInteger('sort')->default(0));
    }
}
```

To get updates through the backend, name the GitHub repository in `plugin.json` (`"repository": "owner/repo"`) and publish releases whose tag is the version (`1.2.0` or `v1.2.0`). The CMS installs the first `.zip` attached to the release — or, without one, the source archive of the tag. Before replacing the folder it backs up the current one (`storage/app/package-backups/`), so **Restore previous version** can go back. Folders that are a Git checkout or come from Composer are not replaced; update those with `git pull` or `composer update`.

### 5.3 `register()` -- PSR-4 Autoloading

Since plugins in `plugins/` live outside the standard Composer autoload paths, you must register a custom autoloader:

```php
public function register(): void
{
    $this->registerAutoloader('Plugins\\MyPlugin');
}
```

> **💡 Tip:** Composer-installed plugins (in `vendor/`) skip this step -- Composer handles their autoloading automatically.
>
> If the provider class cannot be autoloaded, the `PluginManager` registers the same kind of autoloader for the provider's namespace before it loads the provider, so a plugin without `registerAutoloader()` still loads. Calling it explicitly keeps the plugin independent of that fallback.

### 5.4 `boot()` -- Routes, Views, Translations, Extensions

The `boot()` method wires up everything that the framework and users interact with. A typical `boot()` method:

1. Registers view namespaces (with theme override paths)
2. Registers translation namespaces
3. Registers frontend routes
4. Registers backend routes
5. Registers API routes
6. Adds sidebar items
7. Registers search providers (website search and admin quick search)
8. Registers trash tabs and revisionable content types
9. Registers body hooks (injected before `</body>`)
10. Registers head hooks (injected before `</head>`)
11. Registers sitemap providers, dashboard widgets, media usage checkers as needed

Each of these is covered in its own section below.

### 5.5 `install()` -- Schema, Settings, Seeding

Runs exactly once -- the first time an admin enables the plugin. Use it to:

1. Create database tables with `Schema::create()` (always check `Schema::hasTable()` first)
2. Seed default settings via `$this->settings()->set()`
3. Register any page-builder block types via `$this->registerBlockType()`
4. Optionally seed demo content

Demo content can be kept up to date later: if the provider has a public
`refreshDemoContent(): int` method, `php artisan cms:demo-refresh` calls it and
adds the returned count to its summary. Refresh only entries that still exist,
call `createRevision('Demo content refresh')` first when the model uses
`HasRevisions`, and clear translations of replaced texts so language packs
(`seedDemoTranslations()`, called right after) can add their current version.
The blog plugin is the reference.

Register a page-builder block through the base-provider helper -- never touch the
core `BlockType` model directly. `is_core => false` and `plugin_slug` are filled in
for you, and the call is idempotent:

```php
$this->registerBlockType([
    'slug' => 'gallery',      // required
    'name' => 'Gallery',
    'category' => 'content',  // layout | content | interactive
    'icon' => 'bi-images',
]);
```

> **📌 Note:** `install()` runs after `boot()`, so your autoloader is already active and all classes are available.
>
> **Permissions belong in `syncPermissions()`, not `install()`.** `syncPermissions()` runs on *every* enable and is idempotent, so permissions you add in a later plugin version get created and assigned even on installs that already ran `install()` once. Create them with `$this->permissions()->createPermission()` and assign via `$this->permissions()->assignToRole()` there.

### 5.6 `uninstall()` -- Cleanup

Optional. Runs when an admin explicitly uninstalls the plugin (not on disable):

```php
public function uninstall(): void
{
    Schema::dropIfExists('my_table');
    $this->settings()->delete('my_plugin.some_key');
    $this->unregisterBlockType('gallery'); // remove any block types you registered
}
```

---

## 6. 🧰 Contract Accessors / CMS SDK

The `PluginServiceProvider` base class provides typed accessor methods that return contract implementations. These are the **only** way plugins should interact with CMS core features -- never use Eloquent models or repositories directly.

### Accessor Reference

| Accessor | Returns | Description |
|:---------|:--------|:------------|
| `$this->settings()` | `SettingRepository` | Read/write CMS settings |
| `$this->permissions()` | `PermissionManager` | Create permissions, assign/revoke roles |
| `$this->pages()` | `PageManager` | Create and manage CMS pages |
| `$this->media()` | `MediaManager` | Store files and create media records |
| `$this->users()` | `CmsUserProvider` | Query CMS users |
| `$this->routes()` | `RouteRegistrar` | Register web, backend, and API routes |
| `$this->extensions()` | `ExtensionRegistrar` | Register sidebar items, search providers, revisions, trash tabs, hooks, dashboard widgets, sitemap providers and more |
| `cms()` | `CmsManager` | Global helper -- same accessors, usable anywhere (controllers, views, etc.), plus `themes()` (`ThemeManager`), `plugins()` (`PluginManager`) and `version()` (CMS version) |

### SettingRepository

```php
$this->settings()->get(string $key, mixed $default = null): mixed
$this->settings()->set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
$this->settings()->has(string $key): bool
$this->settings()->getByPrefix(string $prefix): array
$this->settings()->delete(string $key): bool
```

### PermissionManager

```php
$this->permissions()->createPermission(string $name): mixed
$this->permissions()->deletePermission(string $name): bool
$this->permissions()->assignToRole(string $roleName, array $permissionNames): void
$this->permissions()->revokeFromRole(string $roleName, array $permissionNames): void
$this->permissions()->userHasPermission(string $permissionName): bool
```

### PageManager

```php
$this->pages()->create(array $attributes): mixed
$this->pages()->findBySlug(string $slug): mixed
$this->pages()->updateBySlug(string $slug, array $attributes): bool
$this->pages()->deleteBySlug(string $slug): bool
$this->pages()->maxOrder(?int $parentId = null): int
$this->pages()->activeForSelect(): array   // [slug => title] of active pages, for <select> inputs
```

### MediaManager

```php
$this->media()->store(string $path, string $content, string $disk = 'public'): string
$this->media()->createRecord(array $attributes): mixed
$this->media()->storeAndRecord(string $path, string $content, array $attributes, string $disk = 'public'): mixed
$this->media()->findByPath(string $path): mixed
$this->media()->delete(mixed $media): bool
```

### CmsUserProvider

```php
$this->users()->first(): mixed
$this->users()->find(int $id): mixed
$this->users()->currentUser(): mixed
$this->users()->currentUserId(): ?int
$this->users()->allForSelect(): array   // [id => name] of all users, for filter/<select> inputs
```

### RouteRegistrar

```php
$this->routes()->web(string $prefix, callable $routes): void
$this->routes()->backend(string $prefix, callable $routes): void
$this->routes()->api(string $prefix, callable $routes): void
$this->routes()->apiProtected(string $prefix, callable $routes): void
```

### ExtensionRegistrar

```php
// Sidebar
$this->extensions()->registerSidebarItem(array $item): void
$this->extensions()->getSidebarItems(): array

// Search on the website
$this->extensions()->registerSearchProvider(SearchProvider $provider): void
$this->extensions()->getSearchProviders(): array

// Quick search in the admin area (Ctrl K); one group per key — the same key again replaces it
$this->extensions()->registerAdminSearchProvider(AdminSearchProvider $provider): void
$this->extensions()->getAdminSearchProviders(): array

// Trash
$this->extensions()->registerTrashTab(array $tab): void
$this->extensions()->getTrashTabs(): array

// Revisions (see section 16a)
$this->extensions()->registerRevisionable(string $type, array $options): void

// Frontend hooks (see section 16b)
$this->extensions()->registerBodyHook(string $viewName): void
$this->extensions()->registerHeadHook(string $viewName): void
$this->extensions()->getBodyHooks(): array
$this->extensions()->getHeadHooks(): array

// View that themes output in their main navigation (e.g. a language menu)
$this->extensions()->registerNavigationHook(string $viewName): void
$this->extensions()->getNavigationHooks(): array

// Setting whose visitor-facing text is translatable (multilingual plugin, see section 10)
$this->extensions()->registerTranslatableSetting(string $key, array $options = []): void

// Settings prefix (tells core which DB keys belong to this plugin)
$this->extensions()->registerSettingsPrefix(string $prefix): void
$this->extensions()->getSettingsPrefixes(): array

// Dashboard widgets (stat cards, widget views, quick actions)
$this->extensions()->registerDashboardWidget(array $widget): void
$this->extensions()->getDashboardWidgets(): array

// Media usage
$this->extensions()->registerMediaUsageChecker(MediaUsageChecker $checker): void
$this->extensions()->getMediaUsageCheckers(): array

// XML sitemap (/sitemap.xml)
$this->extensions()->registerSitemapProvider(SitemapProvider|callable $provider, ?string $key = null, ?string $label = null): void
$this->extensions()->getSitemapProviders(): array
$this->extensions()->registerSitemapLocalizer(SitemapLocalizer $localizer): void
$this->extensions()->getSitemapLocalizer(): ?SitemapLocalizer
```

#### Admin Search Providers (Ctrl K)

The quick search in the admin header searches the whole system: menu items, settings pages, pages, media, users — and whatever plugins add. A plugin contributes a group of results with an `AdminSearchProvider`. Unlike the website search (`SearchProvider`) it finds drafts and inactive content and links into the editor:

```php
use App\Contracts\AdminSearchProvider;
use App\Models\User;
use App\Services\AdminSearch;

class ProjectAdminSearch implements AdminSearchProvider
{
    public function key(): string { return 'projects'; }              // one group per key
    public function label(): string { return 'my-plugin::my-plugin.projects'; }
    public function icon(): string { return 'folder'; }                // Lucide name (bundled set)

    public function search(string $query, User $user, int $limit): iterable
    {
        // Check the permission yourself — the result links must open for this user
        if (! $user->hasPermission('edit-project')) {
            return [];
        }

        // Every word in one of the columns, LIKE wildcards escaped
        $projects = AdminSearch::whereWords(Project::query(), $query, ['name', 'repository'])
            ->limit($limit)
            ->get();

        foreach ($projects as $project) {
            yield [
                'title' => $project->name,
                'url' => route('backend.my-plugin.projects.edit', $project),
                'context' => $project->repository,   // optional, shown next to the title
            ];
        }
    }
}

// In boot():
$this->extensions()->registerAdminSearchProvider(new ProjectAdminSearch);
```

The search starts at two characters and asks every provider for up to five results. Results without `title` or `url` are dropped. A provider that throws is left out (the error is reported), the others still answer. A disabled plugin is not booted, so its group disappears on its own. `label()` goes through `__()`. Icons come from the Lucide set bundled with the core (`resources/icons/lucide/`); an unknown name shows an empty placeholder. Helpers on `App\Services\AdminSearch`: `whereWords()` for queries, `matches()` for lists in memory (ignores case and accents), `mayOpen($routeName, $user)` to check the `permission:` middleware of a route.

Settings pages need no provider: `settings_route` and `search_keywords` in `plugin.json` are enough. The page is listed under the plugin's `name` (translated with `__()`) for users who may open the route; the keywords are matched in the interface language and in English.

#### Sitemap Providers

The core serves an XML sitemap at `/sitemap.xml` (and a `/robots.txt` that points
to it); admins configure both under **Settings > SEO**. The core lists active pages
on its own; a plugin with public URLs adds its content through a `SitemapProvider`.
The blog registers two — posts (with the blog index) and categories — so each gets
its own switch on the settings page:

```php
use App\Contracts\SitemapProvider;

class BlogSitemapProvider implements SitemapProvider
{
    // Stable key: the switch is stored as seo.sitemap_include_{key}
    public function key(): string
    {
        return 'blog';
    }

    // Translation key of the switch label; null = always included, no switch
    public function label(): ?string
    {
        return 'blog::blog.sitemap_posts';
    }

    public function entries(): iterable
    {
        foreach (Post::published()->get() as $post) {
            yield [
                'loc' => route('blog.show', $post->slug), // absolute URL (required)
                'lastmod' => $post->updated_at,           // DateTimeInterface|string|null
                'model' => $post,                         // optional, for language versions
            ];
        }
    }
}

$this->extensions()->registerSitemapProvider(new BlogSitemapProvider);

// or as a callable returning the same entries (key/label optional as above)
$this->extensions()->registerSitemapProvider(fn () => [
    ['loc' => route('events.index')],
], 'events', 'events::events.sitemap_label');
```

Rules:

- Only list what visitors can actually open and search engines may index — no
  drafts, no scheduled content, nothing marked `noindex`.
- Build `loc` with `route()` / `url()` like your frontend does. Invalid entries
  (missing `loc`) are skipped, and a provider that throws is reported and left out
  instead of breaking the sitemap.
- The switch shows a hint below the label when the translation `{label}_hint`
  exists (e.g. `blog::blog.sitemap_posts_hint`). Switched-off providers are not
  queried at all.
- Pass the displayed `model` when it is translatable. With the multilingual plugin
  and more than one language, `/sitemap.xml` becomes a sitemap index pointing to
  `/sitemap-{locale}.xml`; each language sitemap lists the entry under its address
  in that language, with `<xhtml:link rel="alternate" hreflang="…">` links to all
  language versions plus `x-default`. The addresses come from the same
  `Locales::pathUsing()` resolvers the language switcher and the `<head>` hreflang
  use. Entries without a model keep their path and only switch the language prefix.
- The finished XML is cached per base URL for the duration set on the SEO page
  (default one hour). Saving the SEO page, `App\Sitemap\SitemapGenerator::forget()`
  or "Clear cache" in the system info rebuilds it earlier.

`registerSitemapLocalizer()` is the counterpart for language plugins (the multilingual
plugin registers one). A `SitemapLocalizer` names the languages that get their own
sitemap (`locales()`, fewer than two = plain sitemap), the language behind
`x-default` (`defaultLocale()`), and for each entry its address per language
(`alternates(array $entry)`: `['en' => 'https://…', 'de' => 'https://…/de/…']` —
only languages in which the entry really opens, `[]` for entries without language
versions; those appear in the default language's sitemap only).

When an admin switches **Allow search engines** off (Settings > SEO), plugin frontend
routes need no extra work either: every `web` response outside `backend/` and `api/`
gets `X-Robots-Tag: noindex, nofollow` (`App\Http\Middleware\BlockSearchIndexing`),
and HTML pages get `<meta name="robots" content="noindex, nofollow">` through the core
head hook `frontend.partials.robots-meta`. Check the state with
`App\Support\SeoSettings::allowIndexing()` if a plugin has to react to it (e.g. to
skip pinging an external service).

> **`MediaUsageChecker`** lets your plugin report where a media item is used (shown on
> the media delete screen). The interface declares `getUsages(Media $media): array`;
> implement it as `getUsages(mixed $media): array` (widening a parameter type is allowed
> in PHP), so your plugin does not import `App\Models\Media`:
>
> ```php
> class GalleryMediaUsageChecker implements \App\Contracts\MediaUsageChecker
> {
>     public function getUsages(mixed $media): array
>     {
>         // return [['type' => 'gallery-album', 'label' => '…', 'url' => '…'], …]
>     }
> }
> ```

#### Settings Prefix Registration

Register your plugin's settings key prefix so the core knows which keys belong to the plugin:

```php
public function boot(): void
{
    $this->extensions()->registerSettingsPrefix('my-plugin.');
}
```

`GET /api/v1/settings` leaves out every key with a registered prefix, so plugin settings (API keys, SMTP passwords …) are never exposed through the REST API. Settings of type `encrypted` are never returned anyway. The backend's general settings page lists only the core's general settings, with or without a prefix.

#### Dashboard Widgets

Register widgets to appear on the admin dashboard. Three types are supported:

**Stat card** — a count card with icon and link:

```php
$this->extensions()->registerDashboardWidget([
    'type' => 'stat',
    'permission' => 'show-post',
    'label' => 'blog::blog.dashboard_title',   // translation key
    'icon' => 'newspaper',                      // Lucide name (Bootstrap Icons bi-… names are mapped)
    'route' => 'backend.blog.index',            // the card links here
    'count' => fn () => Schema::hasTable('posts') ? DB::table('posts')->whereNull('deleted_at')->count() : 0,
]);
```

The card shows `label`, `count` (a number or a callable returning one), `icon` and links to `route`; its colour is assigned by the dashboard.

**Widget** — a full Blade view:

```php
$this->extensions()->registerDashboardWidget([
    'type' => 'widget',
    'label' => 'my-plugin::messages.title',   // Required — displayed in picker & edit bar
    'icon' => 'puzzle',                        // Lucide name (Bootstrap Icons bi-… names are mapped)
    'permission' => 'show-activity-log',      // default: access-dashboard
    'view' => 'activity-log-admin::partials.dashboard-widget',
    'data' => fn () => ['recentActivity' => \DB::table('activity_logs')->latest()->limit(10)->get()],
]);
```

`data` (an array or a callable returning one) is passed to the view as variables. The widget's ID is derived from `view` (or set `id`), so saved dashboard layouts keep pointing at it.

> **Note:** The widget view is rendered inside a dashboard column. Do **not** add outer margin (`mb-6` etc.) to your partial — spacing is handled by the layout system. Plugin widgets get a transparent wrapper, so your partial brings its own card: wrap it in `<x-ui.card class="flex h-full flex-col" …>` like `plugins/blog/views/admin/partials/dashboard-widget.blade.php`. Users can resize, reorder, and move your widget between rows and columns via the dashboard customizer.

**Quick action** — a sidebar shortcut link:

```php
$this->extensions()->registerDashboardWidget([
    'type' => 'quick_action',
    'permission' => 'create-post',
    'label' => 'blog::blog.create_post',
    'route' => 'backend.blog.create',
    'icon' => 'plus',
]);
```

The `count` and `data` callables run only when the dashboard is built, and only for users with the widget's `permission`, so wrap expensive queries in closures with `Schema::hasTable()` guards for safety.

### The `cms()` Global Helper

The same contracts are available anywhere (controllers, middleware, views) via the global `cms()` helper:

```php
// In a controller
$perPage = cms()->settings()->get('my-plugin.items_per_page', 10);

// In a Blade view
@if(cms()->permissions()->userHasPermission('edit-widget'))
    <a href="...">Edit</a>
@endif
```

---

## 7. 🔄 PSR-4 Autoloading

Plugins in `plugins/` are not part of the Composer autoload map. To load the provider, the `PluginManager` registers a fallback autoloader for the provider's namespace (see [5.3](#53-register----psr-4-autoloading)); register your own in `register()` so all other classes resolve the same way.

### How It Works

Use the `registerAutoloader()` helper provided by the base class:

```php
public function register(): void
{
    $this->registerAutoloader('Plugins\\MyPlugin');
}
```

This maps the `Plugins\MyPlugin\` namespace to the plugin's `src/` directory.

**Resolution steps:**

1. PHP encounters an unknown class, e.g. `Plugins\MyPlugin\Models\Widget`
2. The autoloader checks if the class starts with `Plugins\MyPlugin\`
3. It strips the prefix, leaving `Models\Widget`
4. It converts backslashes to forward slashes: `Models/Widget`
5. It prepends the plugin's `src/` path: `/path/to/plugins/my-plugin/src/Models/Widget.php`
6. If the file exists, it is included via `require_once`

### Namespace Convention

All plugin classes must use the namespace prefix `Plugins\{StudlyCaseSlug}\`. For a plugin with slug `my-plugin`, the namespace is `Plugins\MyPlugin\`.

| Class | Namespace | File Path |
|:------|:----------|:----------|
| Service provider | `Plugins\MyPlugin\MyPluginServiceProvider` | `src/MyPluginServiceProvider.php` |
| Frontend controller | `Plugins\MyPlugin\Http\Controllers\FrontController` | `src/Http/Controllers/FrontController.php` |
| Admin controller | `Plugins\MyPlugin\Http\Controllers\Admin\AdminController` | `src/Http/Controllers/Admin/AdminController.php` |
| API controller | `Plugins\MyPlugin\Http\Controllers\Api\ApiController` | `src/Http/Controllers/Api/ApiController.php` |
| Model | `Plugins\MyPlugin\Models\Widget` | `src/Models/Widget.php` |
| API resource | `Plugins\MyPlugin\Http\Resources\WidgetResource` | `src/Http/Resources/WidgetResource.php` |
| Search provider | `Plugins\MyPlugin\Search\MySearchProvider` | `src/Search/MySearchProvider.php` |

---

## 8. 🛤️ Routes

Plugins can register three types of routes: **frontend** (public web), **backend** (authenticated admin), and **API** (JSON endpoints). All routes are registered in `boot()` via the `$this->routes()` contract.

### 8.1 Frontend Routes

Frontend routes are publicly accessible:

```php
$this->routes()->web('my-plugin', function () {
    Route::get('/', [MyController::class, 'index'])->name('my-plugin.index');
    Route::get('/{slug}', [MyController::class, 'show'])->name('my-plugin.show');
});
```

> **💡 Tip:** You can use a dynamic URL prefix from settings, like the blog plugin does:
> ```php
> $prefix = $this->settings()->get('blog.url_prefix', 'blog');
> $this->routes()->web($prefix, function () { /* ... */ });
> ```

### 8.2 Backend Routes

Backend routes automatically apply `auth`, `verified`, and `permission:access-dashboard` middleware:

```php
$this->routes()->backend('my-plugin', function () {
    Route::get('/', [AdminController::class, 'index'])
        ->name('backend.my-plugin.index');
    Route::get('/create', [AdminController::class, 'create'])
        ->name('backend.my-plugin.create');
    Route::post('/', [AdminController::class, 'store'])
        ->name('backend.my-plugin.store');
    Route::get('/{item}/edit', [AdminController::class, 'edit'])
        ->name('backend.my-plugin.edit');
    Route::put('/{item}', [AdminController::class, 'update'])
        ->name('backend.my-plugin.update');
    Route::delete('/{item}', [AdminController::class, 'destroy'])
        ->name('backend.my-plugin.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'edit'])
        ->name('backend.my-plugin.settings');
    Route::put('/settings', [SettingsController::class, 'update'])
        ->name('backend.my-plugin.settings.update');
});
```

Add per-route permission middleware for fine-grained access control:

```php
Route::delete('/{item}', [AdminController::class, 'destroy'])
    ->middleware('permission:delete-widget')
    ->name('backend.my-plugin.destroy');
```

### 8.3 API Routes

API routes use two groups -- public (read-only) and protected (write). See [Section 17](#17--api-routes) for the full pattern.

```php
// Public: no authentication required
$this->routes()->api('my-plugin', function () {
    Route::get('items', [ApiController::class, 'index']);
    Route::get('items/{id}', [ApiController::class, 'show']);
});

// Protected: requires api.auth + api-access permission
$this->routes()->apiProtected('my-plugin', function () {
    Route::post('items', [ApiController::class, 'store'])
        ->middleware('permission:create-widget');
});
```

---

## 9. 🎨 Views and Theme Overrides

### View Namespacing

Register view namespaces in `boot()` so Blade can find your templates. Use separate namespaces for frontend and admin views:

```php
$pluginPath = $this->getPluginPath();

View::addNamespace('my-plugin', [$pluginPath . '/views/frontend']);
View::addNamespace('my-plugin-admin', [$pluginPath . '/views/admin']);
```

Then render views using the namespace prefix:

```php
// Frontend
return view('my-plugin::index', ['items' => $items]);

// Admin
return view('my-plugin-admin::edit', ['item' => $item]);
```

### Theme Overrides

Themes can override any plugin view. Register every view namespace with the base-class helper `registerPluginViews()` from your `boot()` — never with `View::addNamespace()`, which bypasses the overrides:

```php
// In boot(): register the namespaces with theme-override support
$this->registerPluginViews('my-plugin', $this->getPluginPath() . '/views/frontend');
$this->registerPluginViews('my-plugin-admin', $this->getPluginPath() . '/views/admin');

// Replace some views of another plugin (as the captcha plugin does for form fields)
$this->extendPluginViews('form-builder', $this->getPluginPath() . '/views');
```

A theme overrides a view by placing a file under `themes/{theme}/views/plugins/{namespace}/` — in **every** theme, the bundled `default` theme included. `App\Support\ThemeViews` keeps the namespaces and switches them with the theme in use:

- On the website, a signed-in user's own theme (**Profile → Website theme**) applies to the whole request, plugin views included (middleware `ApplyVisitorTheme`).
- In the admin area, the globally active theme applies — so `my-plugin-admin` views are overridden from the active theme as well.

The active theme lives in the `settings` table, **not** in `config('cms.active_theme')` — never read that config value to locate overrides.

**Resolution order** when you call `view('my-plugin::index')`:

1. `themes/{theme}/views/plugins/my-plugin/index.blade.php` -- theme override
2. views added by another plugin with `extendPluginViews()`
3. `plugins/my-plugin/views/frontend/index.blade.php` -- plugin default

> **💡 Tip:** This allows theme developers to customize the look of any plugin without modifying plugin code.

### Inline Scripts & Content Security Policy

The CMS ships a strict, **nonce-based** Content Security Policy. Every inline
`<script>` you render — in a view, a body/head hook, or a page-builder block —
**must carry the per-request nonce**, or the browser will refuse to execute it
once the policy is enforced:

```blade
{{-- Blade directive (preferred) --}}
<script @cspNonce>
    document.addEventListener('DOMContentLoaded', () => { /* ... */ });
</script>

{{-- Equivalent explicit form --}}
<script nonce="{{ csp_nonce() }}"> /* ... */ </script>
```

Rules of thumb:

- **Inline event handlers are forbidden.** A nonce cannot cover `onclick="…"`,
  `onchange="…"`, etc. Attach listeners from a nonced `<script>` via
  `addEventListener` instead.
- **Inline styles are fine.** `style="…"` attributes and `<style>` blocks stay
  allowed (`style-src` keeps `'unsafe-inline'`).
- **External assets must be same-origin.** Scripts/styles/fonts loaded from a
  third-party origin (e.g. a font CDN) are blocked — self-host them under
  `public/` or ship them with the plugin. (The core policy does allowlist
  YouTube/Vimeo `frame-src` and YouTube thumbnail `img-src` for the video block.)
  If a page really needs a third-party source (e.g. an analytics script the
  visitor consented to), allow it for that one response while the page is built:
  `app(\App\Support\CspSources::class)->allow('script-src', 'https://www.example-analytics.com')`.
  Only `https://` origins (optionally `*.domain`), no paths or keywords, and only
  `script-src`, `connect-src`, `img-src`, `frame-src`, `font-src`, `style-src`,
  `media-src`. The cookie-consent plugin uses this.

> **💡 Tip:** The strict policy is **enforced** (sent as `Content-Security-Policy`,
> not `Report-Only`). An un-nonced inline script or an inline event handler is
> **blocked** by the browser — test your plugin's pages with the console open.

### Admin Views: Confirmations and Delegated Actions

Admin views use the same CSP, so `onclick="return confirm(…)"` is not an option.
The backend layouts include a confirmation dialog in the style of the admin area
(`backend/partials/confirm-modal.blade.php`), and `resources/js/shared/csp-actions.js`
wires up data attributes instead of inline handlers:

```blade
<form method="POST" action="{{ route('backend.my-plugin.destroy', $item) }}"
      data-confirm="{{ __('my-plugin::messages.delete_confirm') }}"
      data-confirm-title="{{ __('my-plugin::messages.delete_title') }}"
      data-confirm-label="{{ __('translation.delete') }}"
      data-confirm-tone="danger">
    @csrf
    @method('DELETE')
    <x-ui.button type="submit" variant="danger">{{ __('translation.delete') }}</x-ui.button>
</form>
```

- `data-confirm` (on a `<form>`) asks before submitting; title, button label and
  `data-confirm-tone="danger"` are optional. Without the dialog (on frontend pages)
  the browser's `window.confirm()` is used.
- From your own nonced script: `const ok = await window.uiConfirm({ title, text, confirm, tone: 'danger' })`
  resolves to `true` or `false` (`uiConfirm('Text')` also works).
- Further attributes: `data-delete-modal='{…}'` opens the shared delete dialog,
  `data-open-modal="name"` / `data-close-modal` open and close modals,
  `data-autosubmit` submits the form when the field changes.
- Icons in markup created by script: `window.uiIcon(name, className)` returns the
  same Lucide SVG as `<x-ui.icon>` as an element, `window.uiIconHtml(name, className)`
  as markup.

### Post-processing of the Finished Page

The core changes every complete HTML response (one that contains `<html`) after
your views, hooks and the theme have rendered. Plan for it:

- **Copyright notice.** `<!-- Copyright © {year} Inverys · Inverys CMS -->` is
  inserted right after the doctype (frontend, backend, error pages). It cannot be
  changed or removed; don't add your own markup before the doctype.
- **Minify HTML** (Settings → General, setting `html.minify`, on by default; not
  in the page-builder preview). HTML comments are removed — except conditional
  comments, comments starting with `[` or `]`, Livewire markers and the copyright
  notice — so never rely on comments in the output. Line breaks and indentation
  next to tags become one space; inside text a run of line breaks and indentation
  becomes a single line break, other spaces in text stay. Attribute values stay
  byte for byte. `<pre>` and `<textarea>` stay unchanged; for whitespace-sensitive
  output (code, ASCII art) use those, not `white-space: pre` on another element.
  Inline `<style>` and `<script>` (no `src`; no type, `module`, `text/javascript`
  or `application/javascript`) are minified with
  [matthiasmullie/minify](https://github.com/matthiasmullie/minify): comments go,
  so keep inline scripts syntactically valid and don't read their source text.
  JSON scripts (`application/json`, `application/ld+json`) are re-encoded
  compactly (invalid JSON stays as it is); other script types such as templates
  stay unchanged.
- **Protect email addresses** (Settings → General, setting `html.protect_emails`,
  on by default; only for visitors who are not signed in, only inside `<body>`).
  A `mailto:` link gets `href="#"` plus the encoded address in `data-cms-email`;
  an address in text shows its `@` through `<span class="cms-at">`; in `title`,
  `aria-label` and `alt` the `@` becomes `(at)`. `public/js/email-protection.js`
  (deferred, added before `</body>`) restores all of it once the DOM is ready.
  So for guests, don't select `a[href^="mailto:"]` or read addresses from the
  markup before `DOMContentLoaded`, and don't expect addresses in HTML you insert
  later to be protected. There is no per-element opt-out; addresses inside
  `<script>`, `<style>`, `<textarea>`, `<select>`, `<svg>`, `<title>`, comments
  and other attributes (`value`, `data-*` …) are left alone. Admins can switch
  the protection off for the whole site.

---

## 10. 🌐 Translations

### Registration

Register translations in `boot()` by adding a namespace to the translator:

```php
$this->app->make('translator')->addNamespace('my-plugin', $pluginPath . '/lang');
```

### File Structure

```
lang/
  en/
    messages.php    # return ['welcome' => 'Welcome!', 'title' => 'My Plugin']
  de/
    messages.php    # return ['welcome' => 'Willkommen!', 'title' => 'Mein Plugin']
```

### Usage

Reference translations with the namespace prefix:

```php
// In PHP
__('my-plugin::messages.welcome')

// In Blade
{{ __('my-plugin::messages.title') }}
```

The translation key format is: `{namespace}::{file}.{key}`.

### Languages and Language Packs

The core only ships English. Other languages are **language packs** — plugins
that register a locale with `App\Support\Locales::register()` (see
[`plugins/language-de`](../plugins/language-de/README.md), which also explains
how to build a French pack). Only registered locales can be chosen as UI or
default language; a missing key always falls back to English.

Ship your plugin's own texts in `lang/{locale}/` as shown above — they are used
whenever that locale is active.

### Translatable Content

To make your plugin's **content** multilingual (titles, slugs, texts in several
languages), use the `App\Models\Concerns\HasTranslations` trait and the editor
helpers `EditLocale`, `TranslationForm` and `Translations::saveFromRequest()`.
Without the Multilingual plugin they do nothing, so the plugin works either way.
Step-by-step: section *Making your own models translatable* in
[`plugins/multilingual/README.md`](../plugins/multilingual/README.md).

Settings whose text visitors see (a banner text, a notice) are made translatable
with `$this->extensions()->registerTranslatableSetting($key, [...])`; they then
appear on the plugin's *Translate texts* page and `setting()` returns the
visitor's language. See *Translatable settings* in the same README.

### Rules for Every Text

- **No text without `__()`** — in Blade, PHP and JavaScript. Backend scripts read
  their texts from the layout: `import { t } from '.../ui/i18n.js'` or
  `window.uiT('key', 'Fallback')`, filled from `translation.script`. Inline
  scripts get their texts via `@json([...])` from Blade.
- **`plugin.json` and `theme.json` are written in English** (name, description,
  setting labels, groups, options). The backend shows them through `__()`, so a
  language pack or your own `lang/<locale>.json` translates them.
- **Dates** go through `format_date()`, `format_datetime()` or
  `format_date_long()` — never `->format('d.m.Y')`. They follow the language
  (and the user's own date format, if set).
- **Mails** go to the user model (`Mail::to($user)`), not to the address string;
  `User::preferredLocale()` then sends them in the recipient's language.
- **Code, comments, tests, scripts and docs are English only.** Other languages
  live only in translation files (`lang/{locale}/`, `lang/{locale}.json`) and
  language packs.

Checks: `php bin/check-translations.php` (every key exists in English and every
language pack), `python3 bin/check-hardcoded-text.py` (visible text without
`__()`; mark intended exceptions with `{{-- i18n-ignore --}}` on the line),
`python3 bin/check-german.py` (German text outside `lang/de` and the German
language pack — it fails CI and `bin/release.sh`; end a line that must stay German,
such as a regex for German input, with the marker `german-ok`, and mention
`german-fixtures` once in a test file whose string literals are German on purpose),
and `CMS_LOG_MISSING_TRANSLATIONS=true` in `.env`, which logs every
untranslated text to `storage/logs/missing-translations.log` while you click
through the pages in another language.

---

## 11. 🗄️ Database Tables

Create database tables in the `install()` method using Laravel's `Schema` builder. Always check if the table already exists to make installation idempotent:

```php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

public function install(): void
{
    if (!Schema::hasTable('widgets')) {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    if (!Schema::hasTable('widget_tag')) {
        Schema::create('widget_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('widget_id')->constrained('widgets')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->unique(['widget_id', 'tag_id']);
        });
    }
}
```

> **📌 Note:** Using `Schema::create` inside `install()` keeps the plugin fully self-contained. Tables are created when an admin enables the plugin for the first time. No migration files needed.

**Best practices:**

- Always wrap each `Schema::create()` in a `Schema::hasTable()` check
- Use foreign key constraints to maintain referential integrity
- Add indexes on columns you query frequently (`is_active`, `published_at`, etc.)
- Use `$table->softDeletes()` if you want trash/restore functionality

### Relating plugin models to core models (without importing them)

Plugin source must not import core model classes (`App\Models\*`). When a plugin
Eloquent model needs a relationship to a core model, resolve the class from the
`cms.models` config map instead of referencing it directly:

```php
// plugins/gallery/src/Models/GalleryAlbum.php
public function images()
{
    return $this->belongsToMany(config('cms.models.media'), 'gallery_album_images', 'album_id', 'media_id');
}

public function creator()
{
    return $this->belongsTo(config('cms.models.user'), 'created_by');
}
```

The map is defined in `config/cms.php` and currently exposes `user`, `media`, and
`page`. This keeps the plugin free of a compile-time dependency on the core classes
while still producing a normal Eloquent relationship.

### Observing core models

To attach an observer to the core models that support activity/audit logging, iterate
the core-owned `cms.observable_models` list rather than hard-coding `App\Models\*`
class names:

```php
$observer = new MyObserver;

foreach (config('cms.observable_models', []) as $model) {
    $model::observe($observer);
}
```

Your own plugin models can be observed directly (they are your classes), e.g.
`\Plugins\MyPlugin\Models\Thing::observe($observer)`.

---

## 12. 🔐 Permissions

Create permissions in `syncPermissions()` and assign them to the built-in roles (`admin`, `editor`, `user`) using the `$this->permissions()` contract. It runs on every enable and after every upgrade, so it must be idempotent — `createPermission()` and `assignToRole()` are:

```php
public function syncPermissions(): void
{
    $permNames = [
        'show-widget', 'create-widget', 'edit-widget', 'delete-widget',
        'widget-settings',
    ];

    foreach ($permNames as $name) {
        $this->permissions()->createPermission($name);
    }

    // Assign to roles
    $this->permissions()->assignToRole('admin', $permNames);
    $this->permissions()->assignToRole('editor', [
        'show-widget', 'create-widget', 'edit-widget',
    ]);
    $this->permissions()->assignToRole('user', ['show-widget']);
}
```

**Permission naming convention:** `{action}-{resource}` (e.g. `create-post`, `edit-blog-category`, `blog-settings`).

### Protecting Routes

```php
// Individual route
Route::delete('/{item}', [Controller::class, 'destroy'])
    ->middleware('permission:delete-widget');
```

### Checking Permissions in Controllers

```php
if (!cms()->permissions()->userHasPermission('edit-widget')) {
    abort(403);
}
```

---

## 13. ⚙️ Settings

Use the `$this->settings()` contract to store plugin configuration. Prefix all keys with your plugin slug to namespace them.

### Seeding Defaults in `install()`

```php
public function install(): void
{
    $defaults = [
        'my-plugin.items_per_page' => ['value' => '10', 'type' => 'integer', 'description' => 'Items per page'],
        'my-plugin.show_sidebar'   => ['value' => '1',  'type' => 'boolean', 'description' => 'Show sidebar'],
        'my-plugin.layout'         => ['value' => 'grid','type' => 'string',  'description' => 'Layout (grid or list)'],
    ];

    foreach ($defaults as $key => $data) {
        if (!$this->settings()->has($key)) {
            $this->settings()->set($key, $data['value'], $data['type'], $data['description']);
        }
    }
}
```

The base-class helper `$this->seedSettings($defaults)` does the same in one call. Register the prefix with `registerSettingsPrefix('my-plugin.')` in `boot()` (see [section 6](#6--contract-accessors--cms-sdk)) so these keys are not returned by the settings API.

### Reading Settings

```php
// In a service provider (using base class accessor)
$perPage = $this->settings()->get('my-plugin.items_per_page', 10);

// In a controller (using global helper)
$perPage = cms()->settings()->get('my-plugin.items_per_page', 10);

// Get all settings for a prefix at once
$settings = cms()->settings()->getByPrefix('my-plugin.');
// Returns: ['items_per_page' => '10', 'show_sidebar' => '1', 'layout' => 'grid']
```

### Settings UI

To provide a settings page:

1. Create a `SettingsController` with `edit()` and `update()` methods.
2. Register the routes as `backend.my-plugin.settings` and `backend.my-plugin.settings.update`.
3. Set `"settings_route": "backend.my-plugin.settings"` in `plugin.json`.

> **💡 Tip:** When `settings_route` is set, the admin plugin list automatically shows a "Settings" link next to the enable/disable toggle.

---

## 14. 📌 Sidebar Integration

Add a link to the admin sidebar by registering a sidebar item in `boot()` via `$this->extensions()`:

```php
$this->extensions()->registerSidebarItem([
    'route'   => 'backend.my-plugin.index',   // Named route
    'icon'    => 'puzzle',                      // Lucide icon name
    'label'   => 'my-plugin::messages.title',   // Translation key
    'active'  => 'backend.my-plugin.*',         // Route pattern for active state
    'section' => 'content',                     // Sidebar section (required)
    'order'   => 30,                            // Position within the section (default 50)
]);
```

### Sidebar Item Fields

| Field | Type | Description |
|:------|:-----|:------------|
| `route` | `string` | Named route for the link target. Items whose route does not exist are hidden. |
| `icon` | `string` | Lucide icon name from the bundled set (`resources/icons/lucide/`, e.g. `newspaper`, `puzzle`). Bootstrap Icons names such as `bi-puzzle` are mapped through `resources/icons/aliases.json`; unknown names show an empty placeholder. Default: `plug`. |
| `label` | `string` | Translation key for the display label. |
| `active` | `string` | Route name pattern used with `request()->routeIs()` to highlight the active item. Use `*` wildcards. |
| `section` | `string` | **Required** — items without a known section are not shown. One of `content`, `users`, `system`, `extensions`. |
| `order` | `int` | (Optional) Sort position within the section; core and plugin items share one scale (core items use 10, 20, 30 …). Default: `50`. |
| `permission` | `string` | (Optional) Permission required to see this item. |

The sidebar renders these items automatically. Multiple plugins can register items -- they are collected from the `ExtensionRegistrar`.

---

## 15. 🔎 Search Provider

Plugins can contribute results to the site-wide search by implementing the `SearchProvider` interface and registering a provider in `boot()`.

### The `SearchProvider` Interface

```php
namespace App\Contracts;

use Illuminate\Support\Collection;

interface SearchProvider
{
    public function key(): string;             // Unique key (used in settings: search.include_{key})
    public function label(): string;           // Human-readable label (translation key)
    public function fields(): array;           // Configurable search fields
    public function search(string $query, array $fieldFlags): Collection;  // Perform the search
}
```

**`fields()` return format:**
```php
['field_key' => ['label' => 'translation.key', 'default' => 'on']]
```

**`search()` result format:** Each item must be an object with `title`, `description`, `url`, and `type` properties.

### Implementation Example

```php
<?php

namespace Plugins\MyPlugin\Search;

use App\Contracts\SearchProvider;
use Illuminate\Support\Collection;
use Plugins\MyPlugin\Models\Widget;

class MyPluginSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'my-plugin';
    }

    public function label(): string
    {
        return 'my-plugin::messages.title';
    }

    public function fields(): array
    {
        return [
            'name'        => ['label' => 'Name', 'default' => 'on'],
            'description' => ['label' => 'Description', 'default' => 'on'],
        ];
    }

    public function search(string $query, array $fieldFlags): Collection
    {
        return Widget::where('is_active', true)
            ->where(function ($q) use ($query, $fieldFlags) {
                if ($fieldFlags['name'] ?? false) {
                    $q->orWhere('name', 'LIKE', "%{$query}%");
                }
                if ($fieldFlags['description'] ?? false) {
                    $q->orWhere('description', 'LIKE', "%{$query}%");
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn ($widget) => (object) [
                'title'       => $widget->name,
                'description' => \Str::limit($widget->description, 160),
                'url'         => route('my-plugin.show', $widget->slug),
                'type'        => 'my-plugin',
            ]);
    }
}
```

### Registration

In `boot()`:

```php
$this->extensions()->registerSearchProvider(
    new \Plugins\MyPlugin\Search\MyPluginSearchProvider()
);
```

### Search Settings

Seed the corresponding settings in `install()` so the admin can toggle which fields are searched:

```php
$searchSettings = [
    'search.include_my-plugin'             => ['value' => 'on', 'type' => 'string', 'description' => 'Include my-plugin in search'],
    'search.my-plugin.fields.name'         => ['value' => 'on', 'type' => 'string', 'description' => 'Search in widget names'],
    'search.my-plugin.fields.description'  => ['value' => 'on', 'type' => 'string', 'description' => 'Search in widget descriptions'],
];

foreach ($searchSettings as $key => $data) {
    if (!$this->settings()->has($key)) {
        $this->settings()->set($key, $data['value'], $data['type'], $data['description']);
    }
}
```

---

## 16. 🗑️ Trash Tab Integration

If your plugin's models use soft deletes, you can add a tab to the CMS trash page so admins can view and restore deleted items.

Register a trash tab in `boot()`:

```php
$this->extensions()->registerTrashTab([
    'type'         => 'widget',                              // Unique type identifier
    'label'        => 'my-plugin::messages.widgets',         // Translation key for the tab label
    'icon'         => 'bi-puzzle',                            // Bootstrap Icon class
    'model'        => \Plugins\MyPlugin\Models\Widget::class, // Model class (must use SoftDeletes)
    'title_column' => 'name',                                 // Column to display as item title
    'permission'   => 'delete-widget',                        // Needed to restore or delete permanently
]);
```

### Trash Tab Fields

| Field | Type | Description |
|:------|:-----|:------------|
| `type` | `string` | Unique identifier for this trash type. |
| `label` | `string` | Translation key for the tab label. |
| `icon` | `string` | Bootstrap Icon CSS class. |
| `model` | `string` | Fully qualified Eloquent model class. Must use `SoftDeletes`. |
| `title_column` | `string` | Database column to display as the item title in the trash list. |
| `permission` | `string` | (Optional) Permission required to restore or permanently delete these items. Without it, the trash's own `manage-trash` permission is enough. |

The trash page automatically queries the model for soft-deleted records and renders a tab with restore and permanent-delete actions.

---

## 16a. 🕘 Revisions

Your content can keep earlier versions just like pages: list, comparison, preview
and restore come from the core. Three steps:

**1. The model** uses `App\Traits\HasRevisions`. Before an update it stores the
previous state of the tracked fields — `$fillable` by default, override
`getRevisionableFields()` to change that. Details:

- A revision is only taken when a user is signed in and a tracked field changes.
- One revision per editing session: saves by the same user within
  `cms.revisions.session_minutes` (`CMS_REVISION_SESSION_MINUTES`, default 10,
  `0` = every save) share one revision.
- At most `revisions.max_per_model` revisions per record (Settings → General,
  default 50); the oldest are pruned.
- The revisions are deleted with the record — with `SoftDeletes` only on
  permanent deletion, not when it moves to the trash.
- With `App\Models\Concerns\HasTranslations`, every language gets its own history:
  `saveTranslations()` stores that language's previous state (only once something
  was translated). Restoring such a revision changes only that language.
- Restoring first stores the current state as a revision (*Before restore*), then
  calls `update()` with the tracked fields of the revision — model events and
  observers run as for any update.

```php
use App\Traits\HasRevisions;

class Project extends Model
{
    use HasRevisions;

    protected $fillable = ['title', 'body', 'published_at'];
}
```

**2. Register the content type** in `boot()`:

```php
$this->extensions()->registerRevisionable('project', [
    'model' => Project::class,
    'permission' => 'edit-project',            // checked on every request; null = everyone with access to the admin area
    'edit_route' => 'backend.projects.edit',   // back to the editor after restoring
    'edit_params' => fn (Project $p) => ['project' => $p],
    'labels' => [                              // field => translation key, shown in the comparison
        'title' => 'projects::projects.title',
        'body' => 'projects::projects.body',
    ],
    // optional: how the content looked — a copy with the old values, nothing is saved
    'preview' => fn (Project $snapshot) => view('projects::show', ['project' => $snapshot])->render(),
]);
```

| Option | Default | Meaning |
|:-------|:--------|:--------|
| `model` | — (required) | Model class with `HasRevisions`. |
| `permission` | `null` | Permission for listing, comparing, previewing, restoring and deleting revisions; `null` = everyone with access to the admin area (`access-dashboard`). |
| `edit_route` | `null` | Route to return to after restoring (with `?tab=revisions`); `null` = back to the previous page. |
| `edit_params` | `null` | `callable(Model): array` with the route parameters; `null` = the model as the only parameter. |
| `labels` | `[]` | Field => translation key for the comparison. |
| `preview` | `null` | `callable(Model $snapshot, Model $current): string` returning the full HTML page; the snapshot is a copy filled with the revision's values. Only for revisions of the original language. |

The type (`project`, lowercase letters, digits and hyphens) becomes part of the
address: `backend/revisions/project/{id}/…` (routes
`backend.revisions.index|show|preview|restore|destroy`); registering a type again
replaces it. The core uses `page`, the blog `post`. Without `preview`, the entries
have no *Preview* action; with it, the core adds a notice bar and sends the page
with `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow`. Fields
without a label are shown with their name in headline case.

**3. The editor** includes the list:

```blade
<x-ui.card :title="__('translation.pages.revisions.title')" icon="history" tone="caution">
    @include('backend.partials.revisions', ['revisionable' => $project, 'revisionType' => 'project', 'revisionsAutoload' => true])
</x-ui.card>
```

`revisionsAutoload` loads the list right away; without it, it loads when the editor
is opened with `?tab=revisions` (pages load it when their tab is clicked). The blog
plugin is the reference: `Post` model, `BlogServiceProvider::boot()` and
`views/admin/edit.blade.php`.

---

## 16b. 🪝 Frontend Hooks (Body & Head)

Plugins can inject HTML into every frontend page using **body hooks** and **head hooks**. The `InjectPluginHooks` middleware renders registered hook views and injects them into the response. Hooks only apply to frontend HTML pages — backend (`/backend/*`), API (`/api/*`) and AJAX requests are excluded. A hook view that throws is skipped silently, so check your hook views with the log and the page source.

### Body Hooks

Body hooks are injected just before the closing `</body>` tag. Use them for UI overlays, floating buttons, or scripts that need DOM access.

```php
// In boot()
$this->extensions()->registerBodyHook('my-plugin::banner');
```

This renders the view `banner` from your `my-plugin` view namespace (e.g. `plugins/my-plugin/views/frontend/banner.blade.php`) on every frontend page.

### Head Hooks

Head hooks are injected just before the closing `</head>` tag. Use them for meta tags, stylesheets, or analytics scripts that must load early.

```php
// In boot()
$this->extensions()->registerHeadHook('my-plugin::head-scripts');
```

### Example: Cookie Consent Plugin

The cookie consent plugin uses both hook types to conditionally inject third-party scripts based on visitor consent:

```php
public function boot(): void
{
    // ...

    // Banner UI (body hook — renders the consent overlay)
    $this->extensions()->registerBodyHook('cookie-consent::banner');

    // Conditional scripts based on consent cookie
    $this->extensions()->registerBodyHook('cookie-consent::body-scripts');
    $this->extensions()->registerHeadHook('cookie-consent::head-scripts');
}
```

The hook views read the `cc_consent` cookie server-side and only render `<script>` tags for categories the visitor has consented to. Add `@cspNonce` to any inline `<script>` a hook emits (see *Inline Scripts & Content Security Policy* in section 9).

### Navigation Hooks

A navigation hook is a view that themes output inside their main navigation, next to search and profile — the multilingual plugin uses it for its language menu:

```php
// In boot()
$this->extensions()->registerNavigationHook('my-plugin::nav-item');
```

Unlike body and head hooks, it is not injected by the middleware: the theme renders it with `@foreach(app('plugin.navigation_hooks') as $navHook) @includeIf($navHook) @endforeach` (the bundled themes do), so it only appears in themes that include this loop.

### Hook Execution Order

1. All registered head hooks are rendered and injected before the first `</head>`
2. All registered body hooks are rendered and injected before the last `</body>`
3. Hooks are rendered in the order they were registered
4. CSP sources allowed via `CspSources` while rendering a hook are added to the response afterwards

> **Note:** Hook views are standard Blade templates. They have access to all CMS helpers (`cms()`, `theme()`, etc.) and can contain any PHP logic, including reading cookies and querying settings.

---

## 17. 🌐 API Routes

Plugins can expose RESTful API endpoints under `/api/v1/{slug}/`. The route registrar provides two groups: **public** and **protected**.

### Route Registration

```php
// Public: read-only endpoints (no authentication required)
$this->routes()->api('my-plugin', function () {
    Route::get('items', [ApiItemController::class, 'index']);
    Route::get('items/{id}', [ApiItemController::class, 'show'])
        ->where('id', '[a-zA-Z0-9_-]+');
});

// Protected: write endpoints (authentication + permissions required)
$this->routes()->apiProtected('my-plugin', function () {
    Route::post('items', [ApiItemController::class, 'store'])
        ->middleware('permission:create-widget');
    Route::put('items/{item}', [ApiItemController::class, 'update'])
        ->middleware('permission:edit-widget');
    Route::delete('items/{item}', [ApiItemController::class, 'destroy'])
        ->middleware('permission:delete-widget');
});
```

### Public vs. Protected

| Type | Middleware | Use Case |
|:-----|:----------|:---------|
| **Public** (`api()`) | JSON responses + API rate limit (no authentication) | Read-only access to published content |
| **Protected** (`apiProtected()`) | JSON responses + API rate limit + `api.auth` + `permission:api-access` | Create, update, delete operations |

Both groups use the prefix `api/v1/{prefix}` and route model binding (`SubstituteBindings`); they do not use the `web` middleware group (no session, no CSRF).

### The `where` Constraint for Flexible ID Lookup

Use `->where('id', '[a-zA-Z0-9_-]+')` on show endpoints to accept both numeric IDs and string slugs:

```php
public function show(string $id)
{
    $item = is_numeric($id)
        ? Widget::where('is_active', true)->findOrFail($id)
        : Widget::where('is_active', true)->where('slug', $id)->firstOrFail();

    return new WidgetResource($item);
}
```

### API Controller Structure

API controllers live in `src/Http/Controllers/Api/` and return JSON responses using Laravel API resources:

```php
<?php

namespace Plugins\MyPlugin\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Plugins\MyPlugin\Http\Resources\WidgetResource;
use Plugins\MyPlugin\Models\Widget;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Widget::where('is_active', true);

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sortField = in_array($request->sort, ['name', 'created_at'])
            ? $request->sort : 'created_at';
        $sortDir = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->input('per_page', 15), 100);

        return WidgetResource::collection($query->paginate($perPage));
    }

    public function show(string $id)
    {
        $item = is_numeric($id)
            ? Widget::where('is_active', true)->findOrFail($id)
            : Widget::where('is_active', true)->where('slug', $id)->firstOrFail();

        return new WidgetResource($item);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:widgets',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item = Widget::create($data);

        return (new WidgetResource($item))->response()->setStatusCode(201);
    }

    public function update(Request $request, Widget $item)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $item->update($data);

        return new WidgetResource($item);
    }

    public function destroy(Widget $item)
    {
        $item->delete();

        return response()->json(['message' => 'Item deleted.']);
    }
}
```

### Blog Plugin API Reference

**Public (no authentication):**

| Method | Endpoint | Description |
|:-------|:---------|:------------|
| `GET` | `/api/v1/blog/posts` | List published posts. Supports `category`, `search`, `featured`, `sort`, `order`, `per_page`. |
| `GET` | `/api/v1/blog/posts/{id}` | Show a single published post by ID or slug. |
| `GET` | `/api/v1/blog/categories` | List all categories with post counts. |
| `GET` | `/api/v1/blog/categories/{id}` | Show a category by ID or slug, with paginated posts. |

**Protected (requires `api.auth` + `api-access`):**

| Method | Endpoint | Permission | Description |
|:-------|:---------|:-----------|:------------|
| `POST` | `/api/v1/blog/posts` | `create-post` | Create a new post. |
| `PUT` | `/api/v1/blog/posts/{post}` | `edit-post` | Update an existing post. |
| `DELETE` | `/api/v1/blog/posts/{post}` | `delete-post` | Soft-delete a post. |
| `POST` | `/api/v1/blog/categories` | `create-blog-category` | Create a new category. |
| `PUT` | `/api/v1/blog/categories/{category}` | `edit-blog-category` | Update a category. |
| `DELETE` | `/api/v1/blog/categories/{category}` | `delete-blog-category` | Delete a category. |

> **📌 Note:** Authentication is via `Authorization: Bearer <token>` header. Generate API tokens in the admin panel under **API Tokens**.

---

## 18. 🧪 Example: Minimal Plugin

This section shows a complete, working plugin from scratch -- the **Announcement Bar** plugin that displays a configurable banner on every frontend page. (The published package `inverys/announcement-bar` is built the same way; this listing is simplified.)

### Directory Structure

```
plugins/
  announcement-bar/
    plugin.json
    composer.json
    src/
      AnnouncementBarServiceProvider.php
      SettingsController.php
    views/
      bar.blade.php          # Body hook
      settings.blade.php     # Settings page
```

### `plugin.json`

```json
{
    "slug": "announcement-bar",
    "name": "Announcement Bar",
    "version": "1.0.0",
    "description": "Displays a configurable announcement bar at the top of every frontend page.",
    "author": "Third-Party Dev",
    "provider": "Plugins\\AnnouncementBar\\AnnouncementBarServiceProvider",
    "settings_route": "backend.announcement-bar.settings"
}
```

### Service Provider

```php
<?php

namespace Plugins\AnnouncementBar;

use App\Services\Plugins\PluginServiceProvider;
use Illuminate\Support\Facades\View;

class AnnouncementBarServiceProvider extends PluginServiceProvider
{
    public function register(): void
    {
        // Maps Plugins\AnnouncementBar\ to src/ (SettingsController)
        $this->registerAutoloader('Plugins\\AnnouncementBar');
    }

    public function boot(): void
    {
        $viewPath = $this->getPluginPath() . '/views';
        View::addNamespace('announcement-bar', $viewPath);

        // Register a body hook so the bar renders on every frontend page
        $this->extensions()->registerBodyHook('announcement-bar::bar');

        // Register a sidebar item for the settings page
        $this->extensions()->registerSidebarItem([
            'label'      => 'Announcement Bar',
            'route'      => 'backend.announcement-bar.settings',
            'icon'       => 'panel-top',
            'section'    => 'extensions',
            'permission' => 'manage-plugins',
        ]);

        // Register the settings route
        $this->routes()->backend('announcement-bar', function () {
            \Illuminate\Support\Facades\Route::get('settings', [SettingsController::class, 'edit'])
                ->name('backend.announcement-bar.settings');
            \Illuminate\Support\Facades\Route::post('settings', [SettingsController::class, 'update'])
                ->name('backend.announcement-bar.settings.save');
        });
    }

    public function install(): void
    {
        // Seed default settings
        $this->settings()->set('announcement_bar.enabled', '0', 'plugin_setting', 'Announcement bar enabled');
        $this->settings()->set('announcement_bar.message', 'Welcome to our site!', 'plugin_setting', 'Message');
        $this->settings()->set('announcement_bar.bg_color', '#fbbf24', 'plugin_setting', 'Background color');
        $this->settings()->set('announcement_bar.text_color', '#1c1917', 'plugin_setting', 'Text color');
        $this->settings()->set('announcement_bar.dismissible', '1', 'plugin_setting', 'Can be dismissed');
        $this->settings()->set('announcement_bar.link_url', '', 'plugin_setting', 'Link URL');
        $this->settings()->set('announcement_bar.link_text', '', 'plugin_setting', 'Link text');
    }

    public function uninstall(): void
    {
        $this->settings()->delete('announcement_bar.enabled');
        $this->settings()->delete('announcement_bar.message');
        $this->settings()->delete('announcement_bar.bg_color');
        $this->settings()->delete('announcement_bar.text_color');
        $this->settings()->delete('announcement_bar.dismissible');
        $this->settings()->delete('announcement_bar.link_url');
        $this->settings()->delete('announcement_bar.link_text');
    }
}
```

> **💡 Tip:** This plugin demonstrates several patterns in minimal form: body hooks for frontend rendering, sidebar items, backend routes for settings, and the full install/uninstall lifecycle with settings cleanup.

---

## 19. 📖 Reference: Blog Plugin

The bundled Blog plugin (`plugins/blog/`) is the canonical reference implementation. It demonstrates every pattern described in this guide.

### Directory Structure

```
plugins/blog/
  plugin.json
  composer.json
  src/
    BlogServiceProvider.php
    Http/
      Controllers/
        BlogController.php              # Frontend: index, show, category, feed, revision preview
        Admin/
          PostController.php             # Backend: CRUD for posts
          SettingsController.php         # Backend: blog settings page
          CategoryController.php         # Backend: category management
        Api/
          PostController.php             # API: public + protected post endpoints
          CategoryController.php         # API: public + protected category endpoints
      Resources/
        PostResource.php                 # API resource transformer for posts
        CategoryResource.php             # API resource transformer for categories
    Models/
      Post.php                           # Soft deletes, revisions, translations
      BlogCategory.php                   # Category model
    Search/
      BlogSearchProvider.php             # Website search
      PostAdminSearch.php                # Admin quick search: posts
      CategoryAdminSearch.php            # Admin quick search: categories
    Services/
      BlogMediaUsageChecker.php          # Where a media item is used
    Sitemap/
      BlogSitemapProvider.php            # Posts (and the blog index) in /sitemap.xml
      BlogCategorySitemapProvider.php    # Categories in /sitemap.xml
  views/
    frontend/
      index.blade.php                    # Blog listing
      show.blade.php                     # Single post
      feed.blade.php                     # RSS feed (XML)
      partials/preview-banner.blade.php  # Notice on draft previews
    admin/
      index.blade.php                    # Post management table
      create.blade.php                   # Create post form
      edit.blade.php                     # Edit post form with revisions
      settings.blade.php                 # Blog settings form
      partials/
        post-form.blade.php              # Shared post form
        dashboard-widget.blade.php       # Dashboard widget
      categories/
        index.blade.php                  # Category management
        create.blade.php
        edit.blade.php
  lang/
    en/blog.php                          # English translations (100+ keys)
    de/blog.php                          # German translations
```

### What It Demonstrates

| Feature | Implementation |
|:--------|:---------------|
| PSR-4 autoloading | `$this->registerAutoloader('Plugins\\Blog')` in `register()` mapping `Plugins\Blog\` to `src/` |
| Frontend routes | `/blog`, `/blog/{slug}`, `/blog/category/{slug}`, `/blog/feed` (prefix configurable via settings) |
| Backend routes | `/backend/blog/*` with `auth`, `verified`, and `permission:access-dashboard` middleware |
| API routes (public) | `GET /api/v1/blog/posts`, `GET /api/v1/blog/posts/{id}`, categories |
| API routes (protected) | `POST/PUT/DELETE` posts and categories with `api.auth` + per-route permissions |
| View namespacing | `blog::` for frontend, `blog-admin::` for admin views |
| Theme overrides | `registerPluginViews()` for `blog` and `blog-admin`: `themes/{theme}/views/plugins/blog/` wins in every theme |
| Translations | `blog::blog.title` with EN and DE support |
| Sidebar item | "Posts" link with the `newspaper` icon in the `content` section (`order` 30) |
| Search provider | `BlogSearchProvider` -- searches title, content, excerpt, meta |
| Admin quick search | `PostAdminSearch` and `CategoryAdminSearch`; settings page found via `settings_route` + `search_keywords` |
| Revisions | `HasRevisions` on `Post`, `registerRevisionable('post', …)` with preview, list in `views/admin/edit.blade.php` |
| Sitemap | `BlogSitemapProvider` and `BlogCategorySitemapProvider`, each with its own switch |
| Dashboard | Stat card, quick action and widget via `registerDashboardWidget()` |
| Media usage | `BlogMediaUsageChecker` |
| Trash tab | Soft-deleted posts in the CMS trash page (restore/delete needs `delete-post`) |
| Database tables | `posts`, `blog_categories`, `blog_category_post` created in `install()` |
| Permissions | 12 permissions in `syncPermissions()`: `show-post`, `create-post`, `edit-post`, `delete-post`, `publish-post`, `schedule-post`, `draft-post`, category CRUD, `blog-settings` |
| Settings | 20+ settings: URL prefix, posts per page, columns, sorting, visibility toggles, search fields |
| Settings route | `backend.blog.settings` linked in `plugin.json` |
| Demo content | 6 posts and 3 categories seeded in `install()` (CLI only) |
| Page tree entry | Creates a "Blog" page in the CMS navigation via `$this->pages()->create()` |
| Media seeding | Generates demo SVG images via `$this->media()->storeAndRecord()` |

---

## 20. 📤 Distribution

Inverys CMS supports two distribution methods: **ZIP upload** (for direct sharing) and **Composer packages** (for the PHP ecosystem).

### 20.1 ZIP Upload

Package your plugin as a ZIP archive for manual upload through the admin panel.

**Packaging:**

```bash
cd plugins/my-plugin
zip -r my-plugin-1.0.0.zip . -x "*.DS_Store" -x "__MACOSX/*"
```

**Required contents:**

```
my-plugin-1.0.0.zip
  plugin.json          # At the ZIP root (or inside one wrapper folder)
  src/
    MyPluginServiceProvider.php
    ...
  views/
  lang/
```

**Security checks performed on upload:**

| Check | Description |
|:------|:------------|
| Valid ZIP | File must be a valid ZIP archive |
| No path traversal | No `..` and no absolute paths in entries |
| Blocked files | `.htaccess`, `.htpasswd`, `.env`, `web.config` |
| Blocked extensions | `.phar`, `.sh`, `.bash`, `.bat`, `.cmd`, `.exe`, `.com`, `.msi`, `.dll`, `.so` |
| `plugin.json` exists | At the root of the archive or one folder deep (wrapper folder) |
| Valid JSON with `slug` | `plugin.json` must parse and contain a `slug` |
| Slug format | Lowercase letters, digits and hyphens; starts and ends with a letter or digit |
| `provider` present | The `provider` field must be set |
| No downgrade | If the plugin is already installed, an older `version` is rejected; a newer one is an update (the current folder is backed up first) |

**Upload process:**

1. Admin navigates to **Extensions > Plugins > Upload**
2. Selects the ZIP file
3. CMS validates the archive (security checks above)
4. Extracts to `plugins/{slug}/`
5. Plugin appears in the plugin list and can be enabled

### 20.2 Composer Package

Distribute your plugin as a Composer package for installation via `composer require`.

**Full `composer.json`:**

```json
{
    "name": "your-vendor/your-plugin",
    "description": "Description of your plugin.",
    "type": "inverys-plugin",
    "version": "1.0.0",
    "license": "MIT",
    "authors": [
        { "name": "Your Name", "email": "you@example.com" }
    ],
    "extra": {
        "inverys": {
            "slug": "your-plugin"
        }
    },
    "autoload": {
        "psr-4": {
            "Plugins\\YourPlugin\\": "src/"
        }
    },
    "require": {
        "php": "^8.2"
    }
}
```

> **⚠️ Warning:** The `type` field **must** be `"inverys-plugin"` -- this is how the CMS identifies plugin packages in `vendor/`.

**How discovery works:**

1. `composer require your-vendor/your-plugin`
2. Package installs to `vendor/your-vendor/your-plugin/`
3. Composer updates `vendor/composer/installed.json`
4. `PluginManager::discover()` scans `installed.json` for packages with `"type": "inverys-plugin"`
5. Reads `extra.inverys.slug` to determine the plugin slug
6. Resolves the install path from Composer's metadata
7. Loads `plugin.json` from the resolved path
8. Plugin appears in **Extensions > Plugins**

**Key differences from ZIP plugins:**

| Aspect | ZIP Plugin | Composer Plugin |
|:-------|:-----------|:----------------|
| Location | `plugins/{slug}/` | `vendor/{vendor}/{package}/` |
| Autoloading | `registerAutoloader()` in `register()` | Automatic via Composer PSR-4 |
| Updates | Re-upload a newer ZIP, or the **Update** button with `repository` set | `composer update` |
| Dependencies | Manual | Composer resolves automatically |
| Version control | Manual | Packagist / VCS |

> **💡 Tip:** You can develop locally in `plugins/` during development, then add a `composer.json` when ready to distribute. The plugin works identically in both locations.

---

*Built with Inverys CMS plugin architecture. For questions, see the [Blog plugin source](../plugins/blog/) as a reference implementation.*
