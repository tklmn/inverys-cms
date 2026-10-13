# Testing

The test suite uses **PHPUnit** with **SQLite in-memory** for fast, isolated tests.

## Running Tests

```bash
# Run all tests
php artisan test

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature tests
php artisan test --testsuite=Feature

# Run a specific test class
php artisan test --filter=PageControllerTest

# Run with coverage
php artisan test --coverage

# Clear the config cache first, then run all tests
composer test
```

## Test Structure

| Category | Location | What it covers |
|----------|----------|----------------|
| **Unit / Models** | `tests/Unit/Models/` | User, Page, Media, ApiToken, Role, Permission, Setting, Plugin, BlockType, PageCategory, CmsNotification, UserSetting |
| **Unit / Services** | `tests/Unit/Services/` | BlockValidator, BlockRenderer, BlockRegistry, BlockDesignOptions, BlockLayoutOptions, CmsManager, ThemeManager, PluginManager, PackageInstaller, NotificationService |
| **Unit / Middleware** | `tests/Unit/Middleware/` | CheckPermission, CheckRole, EnsureUserIsActive, ApiAuthenticate, ApiRateLimit, ForceJsonResponse, SetUserLocale, RedirectIfInstalled, RedirectIfNotInstalled |
| **Unit / Helpers** | `tests/Unit/Helpers/` | HtmlHelper XSS sanitization, CSS sanitization |
| **Unit / Support** | `tests/Unit/Support/` | Block text, demo content, localization helpers, SBOM inventory |
| **Feature / Admin** | `tests/Feature/Admin/` | Dashboard, Pages, Media, Users, Roles, Permissions, Settings, General settings, SEO settings, Plugins, Themes, Trash, ApiTokens, PageCategories, Notifications, Logs, SystemInfo, lockout guard |
| **Feature / API** | `tests/Feature/Api/` | Auth, Pages, Media, Settings, Users, Plugins, Themes, BlockTypes, JSON:API format — CRUD, pagination caps, permission enforcement |
| **Feature / Security** | `tests/Feature/Security/` | Role permission matrix, privilege escalation, host header, input and upload hardening, installer lock, two-factor bypass and replay, user enumeration, security headers (CSP, X-Frame-Options, HSTS, etc.), security settings, security.txt |
| **Feature / Auth** | `tests/Feature/Auth/` | Login, registration, password reset, confirmation and update, email verification and change, two-factor authentication |
| **Feature / Updates** | `tests/Feature/Updates/` | Update checks, plugin/theme package updates, CMS update from the backend |
| **Feature / Plugins** | `tests/Feature/Plugins/` | Activity Log, Announcement Bar, Blog (incl. API, preview, revisions), Captcha, Cookie Consent, Form Builder, Gallery, Multilingual (incl. translation revisions) |
| **Feature** (top level) | `tests/Feature/` | Cross-cutting behaviour, e.g. copyright notice, email protection, HTML minification and icon sprite (`LeanHtmlTest`), revisions, storage link, extension assets, admin search, installer, sitemap, search |

## Base Test Class

All tests extend `Tests\TestCase`, which provides helper methods for common setup:

```php
// Create users with specific roles
$admin  = $this->createAdmin();   // User with admin role + all permissions
$editor = $this->createEditor();  // User with editor role (limited permissions)
$user   = $this->createUser();    // User with no backend access

// Seed the full RBAC system (permissions, admin/editor roles)
$this->seedPermissions();

// Mark the app as installed (creates the storage/installed marker and
// switches two-factor authentication off, so logins are not challenged)
$this->markAsInstalled();
```

Two traits in `tests/Concerns/` cover recurring setups:

- `LoadsPlugins` — `$this->loadPlugins(['blog' => 'Blog'])` enables plugins:
  creates the record, registers the provider and runs its `install()` (tables).
- `PreservesInstalledMarker` — for installer tests that toggle
  `storage/installed`: the real marker is restored after every test.

## Factories

The core models have factories in `database/factories/` (User, Page, Media,
Role, Permission, Setting, Plugin, PageCategory, BlockType, ApiToken).

### User Factory

```php
User::factory()->create();
User::factory()->create(['is_active' => false]);
```

### Page Factory

```php
Page::factory()->create();                           // Basic page
Page::factory()->root()->create();                   // Home page (is_root, slug "/")
Page::factory()->inactive()->create();               // Inactive page
Page::factory()->withBlocks()->create();             // Page with a sample text block (or pass your own blocks)
Page::factory()->childOf($parentPage)->create();     // Child of another page
```

### Media Factory

```php
Media::factory()->create();          // Default media item
Media::factory()->image()->create(); // Image (image/jpeg)
Media::factory()->pdf()->create();   // PDF document
Media::factory()->video()->create(); // Video file
```

### Role & Permission Factories

```php
Role::factory()->create();
Role::factory()->system()->create();    // System role (is_system = true)
Permission::factory()->create();
```

### Other Factories

```php
Setting::factory()->create();
Plugin::factory()->create();
Plugin::factory()->enabled()->create();
Plugin::factory()->system()->create();
PageCategory::factory()->create();
BlockType::factory()->create();
BlockType::factory()->plugin('blog')->create();
User::factory()->unverified()->create();
```

### API Token Factory

```php
// Create a token and get both the model and plain-text value
$result = ApiTokenFactory::createWithPlainText($user);
$token  = $result['token'];        // ApiToken model
$plain  = $result['plain_text'];   // Plain-text token for Authorization header

// Expired token
$result = ApiTokenFactory::createWithPlainText($user, [
    'expires_at' => now()->subHour(),
]);

// Read-only token
$result = ApiTokenFactory::createWithPlainText($user, ['abilities' => ['read']]);
```

`ApiToken::factory()->expired()` creates an expired token without the plain text.

## Writing New Tests

### Unit Test Example

```php
namespace Tests\Unit\Models;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_generates_slug_from_title(): void
    {
        // The factory sets its own slug; null lets the model derive it
        $page = Page::factory()->create(['title' => 'My New Page', 'slug' => null]);

        $this->assertEquals('my-new-page', $page->slug);
    }
}
```

### Feature Test Example

```php
namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_admin_can_view_page_index(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.pages.index'))
            ->assertOk();
    }

    public function test_user_without_permission_cannot_delete_page(): void
    {
        $user = $this->createUser();
        $page = \App\Models\Page::factory()->create();

        $this->actingAs($user)
            ->delete(route('backend.pages.destroy', $page))
            ->assertForbidden();
    }
}
```

### API Test Example

```php
namespace Tests\Feature\Api;

use Database\Factories\ApiTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
    }

    public function test_can_list_pages_via_api(): void
    {
        $admin = $this->createAdmin();
        $result = ApiTokenFactory::createWithPlainText($admin);

        $this->getJson('/api/v1/pages', [
            'Authorization' => 'Bearer ' . $result['plain_text'],
        ])->assertOk();
    }
}
```

### Plugin Test Example

Plugin tests need the plugin's record, its service provider, its tables and
refreshed route lookups — `LoadsPlugins` does all of it:

```php
namespace Tests\Feature\Plugins\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LoadsPlugins;
use Tests\TestCase;

class AdminPostControllerTest extends TestCase
{
    use LoadsPlugins, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->markAsInstalled();
        $this->loadPlugins(['blog' => 'Blog']);
    }

    public function test_admin_can_view_posts(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('backend.blog.posts.index'))
            ->assertOk();
    }
}
```

## Configuration

Test configuration is in `phpunit.xml`:

- **Environment**: `APP_ENV=testing`, `APP_URL=http://localhost`
- **Database**: SQLite in-memory (`:memory:`)
- **Cache**: Array driver
- **Session**: Array driver
- **Queue**: Sync driver
- **Mail**: Array driver (no emails sent)
- **Passwords**: `BCRYPT_ROUNDS=4` for speed

## Check Scripts

Besides the test suite, `bin/` holds checkers for things tests do not see. Only
`check-german.py` runs in CI; the others are run by hand after larger changes
(see *Checks* in [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md#7-checks)).

| Script | Checks | Options / output |
|--------|--------|------------------|
| `python3 bin/check-german.py` | German text outside translation files (`lang/de`, the German language pack) in every tracked file | Lists `path:line: text`, exit code 1 on findings, otherwise *No German text outside translation files.* A line that must stay German ends with `german-ok`; a test with German fixtures mentions `german-fixtures` once |
| `php bin/check-translations.php` | Every translation key used in the code exists in English and in the checked languages | `--locale=fr` (default `de`), `--unused`, `--dynamic`; summary *N keys in code, N gaps*, exit code 1 on gaps |
| `python3 bin/check-hardcoded-text.py` | Visible text in Blade views that is not translated | `--summary` (count per file); summary *N findings in N files*; mark intended spots with `{{-- i18n-ignore --}}` |
| `bash bin/lint-views.sh` | PHP syntax of the compiled views (run `php artisan view:cache` first) | `BROKEN: <view>` per finding, summary *N broken views* |
| `bash bin/lint-inline-js.sh` | Inline scripts of every rendered backend page (`node --check`) | Summary *All inline scripts are error-free.* or *N page(s) with broken inline scripts.* |
| `python3 bin/audit-views.py` | Backend views against the rules of the design system | `--strict` (exit code 1 on findings); summary *Total: N hits* or *No deviations.* |
| `python3 bin/check-widths.py` | Content area of every backend page uses the full width; raw `<x-…>` tags | `--verbose`; lines `NARROW` and `RAW` per page |
| `node bin/check-browser.cjs` (`npm run check:browser`) | Every backend page in Chromium (Playwright), desktop and 390 px | Page paths as arguments, `SHOTS=<dir>` for screenshots, `GUEST_LOCALES`; summary *… page loads (desktop + mobile), N problems* |

The page checkers (`lint-inline-js.sh`, `check-widths.py`, `check-browser.cjs`)
take their page list from `php artisan route:list` and need a logged-in session
in `$COOKIE_JAR` (default `/tmp/inverys-cookies.txt`); `BASE_URL` defaults to
`http://localhost/inverys/cms/public`.

## Continuous Integration

`.github/workflows/ci.yml` runs on every push to `master` and on pull requests:

- **PHP** (8.4): `vendor/bin/pint --test` (code style), `python3 bin/check-german.py`
  (English only outside translation files), `php artisan test`, and `composer audit`.
- **Assets** (Node 20): `npm ci`, `npm audit --audit-level=high`, and `npm run build`.

A green pipeline is the merge gate — keep the suite, the German check and both
audits clean.

## Cutting a Release

Releases are automated by `bin/release.sh`, which keeps the version in sync across
`config/cms.php`, `package.json`, and the SBOM, and never lets them drift:

```bash
# 1. Add a "## [X.Y.Z] - YYYY-MM-DD" section to the top of CHANGELOG.md.
# 2. From a clean master (only CHANGELOG.md changed):
bash bin/release.sh X.Y.Z
```

It bumps the version everywhere, regenerates the SBOM (`sbom/generate.sh`) and
checks its root version, then runs the gates — `python3 bin/check-german.py`,
the full test suite, `composer audit` and `npm audit --audit-level=high`. Only
then it commits, pushes, tags (no `v` prefix), and creates the GitHub release
using the CHANGELOG section as the notes, with the source archive, both SBOMs
and `SHA256SUMS` attached. Publishing the release triggers
`.github/workflows/release-package.yml`, which builds the update package
`inverys-cms-<version>.zip` (with `vendor/` and the built assets) plus its
`.sha256` and attaches them to the release.
