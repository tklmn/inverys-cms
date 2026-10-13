<p align="center">
  <img src="public/images/logo.svg" alt="Inverys CMS" width="240">
</p>

<p align="center">
  A modern, modular content management system built with Laravel 13.<br>
  Drag-and-drop page builder &middot; swappable themes &middot; plugin architecture &middot; RESTful API &middot; full RBAC
</p>

<p align="center">
  <a href="https://github.com/tklmn/inverys-cms/actions/workflows/ci.yml"><img src="https://github.com/tklmn/inverys-cms/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <img src="https://img.shields.io/badge/PHP-8.4+-777BB4?logo=php&logoColor=white" alt="PHP 8.4+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/TinyMCE-8.9-22A7F0?logo=tinymce&logoColor=white" alt="TinyMCE 8.9">
  <img src="https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License">
</p>

---

## Features

| | Feature | Description |
|---|---|---|
| :bricks: | **Page Builder** | 23 core block types (layout, hero, stats, pricing, content, interactive) with drag-and-drop, nested containers, design options picked from lists (background, spacing, alignment, icons — no CSS classes), TinyMCE WYSIWYG, media browser, auto-save |
| :mag: | **Search** | Plugin-based site search (pages, blog posts, forms, gallery albums) with extensible providers, per-provider field toggles, configurable pagination |
| :newspaper: | **[Blog Plugin](plugins/blog/)** | Standalone plugin with posts, categories, RSS feed, REST API, configurable URL prefix, layout, and display settings |
| :wastebasket: | **Trash Management** | Unified soft-delete trash for pages, users, media, roles, permissions, plugins, theme settings and plugin content (posts, forms, albums) — restore or permanently delete |
| :art: | **Themes** | Swappable frontend themes with JSON-schema settings, customizable regions, color pickers, custom CSS, and built-in file editor. Install via ZIP upload or Composer. Two bundled themes (default + modern, both light and dark) plus the Composer starter theme |
| :electric_plug: | **Plugins** | Contract-based SDK with `cms()` helper, lifecycle hooks, settings routes. Install via ZIP upload or `composer require`. Nine bundled plugins plus the Composer announcement bar |
| :package: | **Plugin SDK** | 13 contracts (`SettingRepository`, `PageManager`, `MediaManager`, `PermissionManager`, `RouteRegistrar`, `ExtensionRegistrar`, `CmsUserProvider`, `SearchProvider`, `AdminSearchProvider`, `MediaUsageChecker`, `SitemapProvider`, `SitemapLocalizer`, `ContentTranslator`) plus base-provider helpers (`registerBlockType()`) and config maps (`cms.models`, `cms.observable_models`) — plugin source never imports core model classes |
| :globe_with_meridians: | **REST API** | Full JSON API under `/api/v1/` with token auth, rate limiting, and permission-gated endpoints for pages, media, users, settings, themes, plugins, and blog |
| :lock: | **RBAC** | Role-based access control with granular permissions for every feature |
| :earth_americas: | **[Multilingual](plugins/multilingual/)** | Content in several languages: pages, posts, menus and settings per language with their own URLs (`/de/…`), fallback to the original, translation status in every list. German language pack ([language-de](plugins/language-de/)) with the full UI and German demo content |
| :sparkles: | **Backend UI** | Design system on Tailwind v4 with tokens for light and dark mode, Lucide icons, container-query lists — documented in [DESIGN-SYSTEM.md](docs/DESIGN-SYSTEM.md) |
| :mag_right: | **Quick Search** | System-wide search in the admin area (Ctrl/⌘ K): menu items, settings pages by topic, pages, media, users, blog posts and categories, forms and gallery albums — limited to what the user may open, extensible by plugins |
| :camera: | **Media Library** | Upload with thumbnail generation, alt text, search, global picker modal, relative path storage |
| :email: | **[Mail Providers](plugins/mail-providers/)** | SMTP, Microsoft 365 (Graph API) and Gmail API with OAuth2, test email, and runtime config |
| :mag: | **SEO** | Per-page meta title, description, robots, Open Graph, canonical URL, aria labels |
| :closed_lock_with_key: | **Two-Factor Auth** | Global 2FA with email codes or TOTP (authenticator app). QR code setup, recovery codes, per-user opt-out, admin toggle |
| :shield: | **Security** | XSS sanitization, CSS sanitization, rate limiting, CSRF, permission middleware, nonce-based Content-Security-Policy. Admin-configurable password policy, registration switch, login protection, API rate limits, session timeout, data retention, and `security.txt` |
| :clock3: | **Revisions** | Automatic version history for pages and blog posts (one revision per editing session), separate history per language, block-level comparison with the current or the next version, preview of an old version, restore that keeps the current state, configurable retention limit; plugins can add revisions for their content |
| :arrows_counterclockwise: | **Updates** | Update the CMS from the backend (release package with checksum check, database and file backup, maintenance mode, restore of the previous version); plugins and themes update from their lists with a backup of the previous version |
| :zap: | **Lean Page Output** | Minified HTML with inline CSS/JS (bundled, server-side), icon sprite, copyright notice in the page source, and email addresses on the website hidden from spam bots — for every theme and plugin |
| :bar_chart: | **Dashboard** | Row-based layout builder with 6 column presets, drag-and-drop widgets between rows/columns, per-widget width control, spacers, and per-user layout persistence |
| :rocket: | **Web Installer** | Zero-CLI setup wizard — auto-creates `.env`, generates key, runs migrations, seeds data. Step-by-step progress with resume on reload |
| :clipboard: | **[Form Builder](plugins/form-builder/)** | Drag-and-drop form editor with 16 field types, column layouts, file uploads, submissions inbox, CSV export, email notifications, sender copy, confirmation page, spam protection, page builder integration |
| :scroll: | **[Activity Log](plugins/activity-log/)** | Automatic CRUD and auth event tracking with field-level diffs, filterable index, dashboard widget, auto-cleanup |
| :cookie: | **[Cookie Consent](plugins/cookie-consent/)** | GDPR-compliant consent banner with category-based opt-in, external service management, and conditional script injection |
| :framed_picture: | **[Gallery](plugins/gallery/)** | Image albums from the media library, page builder block, lightbox, responsive grid |
| :robot: | **[Captcha](plugins/captcha/)** | Built-in math captcha or external providers (reCAPTCHA v3, Turnstile, hCaptcha, CaptchaFox, Friendly Captcha) for forms |
| :iphone: | **Responsive Navigation** | Hamburger menu on mobile; on desktop, entries that don't fit move into a "More" dropdown — sub-navigation included |

## Requirements

- **PHP** >= 8.4 with the extensions PDO, Mbstring, OpenSSL, Tokenizer, Ctype, JSON, Fileinfo, DOM and GD (thumbnails)
- **Composer** 2.x and **Node.js** 20.19+ or 22.12+ — only to build from source; the release package `inverys-cms-<version>.zip` already contains `vendor/` and the built assets
- **MySQL** / MariaDB / PostgreSQL / SQLite

## Quick Start

### Web Installer (recommended)

The web installer handles everything through the browser — no terminal commands needed after the initial setup.

```bash
# 1. Clone & install dependencies
git clone <repo-url> inverys-cms && cd inverys-cms
composer install && npm install && npm run build

# 2. Open in browser
php artisan serve
# Visit http://localhost:8000 → auto-redirects to /install
```

The installer wizard will:
1. Check system requirements (PHP version, extensions, directory permissions)
2. Configure your database connection (SQLite, MySQL, MariaDB, or PostgreSQL)
3. Set site name, URL, language, and select optional demo content (sample pages with their images, page categories, blog demo posts) — without demo content the site starts with a ready home page and footer
4. Create the admin account
5. Run migrations, seed roles/permissions/settings, enable the bundled system plugins, link storage — all with live step-by-step progress

If the page reloads during installation, the wizard resumes from the last completed step.

### Manual Setup (CLI)

```bash
# 1. Clone & install
git clone <repo-url> inverys-cms && cd inverys-cms
composer install && npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# Configure DB_* variables in .env

# 3. Database & assets
php artisan migrate --seed
php artisan storage:link
npm run build

# 4. Serve
php artisan serve
```

A manual `--seed` install creates a default admin `admin@example.com` with a **strong random password that is printed once to the console** (`DefaultAdminUserSeeder`) — copy it from the seed output, as it is only stored hashed and cannot be recovered. Outside `APP_ENV=production` it also creates demo users (`editor`/`user`/`blocked@example.com`) that share the password **`password`** and are for development only. **Change or remove any default-password accounts before the site goes public.** The web-installer path has no hard-coded credentials: you set the admin account yourself, and it creates no demo users. See the [User Guide](docs/USER-GUIDE.md#-security-after-installation).

### Shared Hosting / Webhoster

1. Download the release package `inverys-cms-<version>.zip` (contains `vendor/` and the built assets) — or build it locally: `git clone`, `composer install`, `npm install && npm run build`
2. Upload all files to your web host, e.g. via FTP (including `vendor/` and built assets)
3. Point your domain to the `public/` directory (if that is not possible, the root `.htaccess` serves only `public/` on Apache)
4. Visit your domain — the installer starts automatically (`.env` and `APP_KEY` are created on first visit)

FTP clients often skip empty folders and symlinks: the CMS recreates missing `storage/` folders and the relative `public/storage` link on the next request, so no SSH access is needed.

## Documentation

| Guide | Description |
|-------|-------------|
| :book: **[User Guide](docs/USER-GUIDE.md)** | Installation, dashboard and quick search, pages, page builder and revisions, blog, media, users, settings, languages, themes, plugins, updates, forms, notifications, mail, SEO, search, security, system info |
| :art: **[Theme Development](docs/THEME-DEVELOPMENT.md)** | Create custom themes: directory structure, `theme.json` manifest, settings, regions, Blade views, helpers, plugin view overrides, custom CSS |
| :electric_plug: **[Plugin Development](docs/PLUGIN-DEVELOPMENT.md)** | Build plugins: service providers, lifecycle, routes, views, translations, permissions, settings, sidebar integration, API endpoints |
| :satellite: **[REST API](docs/API.md)** | Versioned `/api/v1`: bearer-token auth, permissions, default resource format, and the opt-in JSON:API (`application/vnd.api+json`) representation |
| :test_tube: **[Testing](docs/TESTING.md)** | PHPUnit setup, test structure, factories, base test class helpers, writing unit/feature/API tests |
| :lock: **[Security Policy](SECURITY.md)** | Supported versions, coordinated vulnerability reporting, built-in security controls, and the CycloneDX SBOM under [`sbom/`](sbom/) |
| :shield: **[Security Hardening](docs/SECURITY-HARDENING.md)** | Production checklist: HTTPS, reverse proxy, trusted hosts, file permissions, `APP_KEY` rotation, backups, updates, `security.txt` |
| :clipboard: **[CRA Readiness](docs/CRA-READINESS.md)** | EU Cyber Resilience Act checklist and the [vulnerability handling process](docs/SECURITY-PROCESS.md) |

## API Reference

All authenticated endpoints require a Bearer token and the `api-access` permission.

```
POST   /api/v1/auth/login          # Get token
POST   /api/v1/auth/logout         # Revoke token
GET    /api/v1/auth/me             # Current user
```

### Core API

| Resource | Endpoints | Permissions |
|----------|-----------|-------------|
| **Pages** | `GET` `POST` `/pages`, `GET` `PUT` `DELETE` `/pages/{id}` | `show-page`, `create-page`, `edit-page`, `delete-page` |
| **Media** | `GET` `POST` `/media`, `GET` `DELETE` `/media/{id}` | `show-media`, `create-media`, `delete-media` |
| **Users** | `GET` `/users`, `GET` `/users/{id}` | `show-user` |
| **Settings** | `GET` `/settings`, `PUT` `/settings/{key}` | `show-settings`, `edit-settings` |
| **Themes** | `GET` `/themes`, `PUT` `/themes/active` | `manage-themes` |
| **Plugins** | `GET` `/plugins`, `PUT` `/plugins/{slug}` | `manage-plugins` |
| **Blocks** | `GET` `/blocks/types` | `manage-blocks` |

### Blog API

Public read endpoints (no authentication required):

| Endpoint | Description |
|----------|-------------|
| `GET /api/v1/blog/posts` | List published posts (supports `?category=`, `?search=`, `?featured=`, `?sort=`, `?per_page=`) |
| `GET /api/v1/blog/posts/{id\|slug}` | Single published post with author and categories |
| `GET /api/v1/blog/categories` | All categories with post counts |
| `GET /api/v1/blog/categories/{id\|slug}` | Category detail with paginated posts |

Protected write endpoints (Bearer token + permissions):

| Endpoint | Permission |
|----------|------------|
| `POST /api/v1/blog/posts` | `create-post` |
| `PUT /api/v1/blog/posts/{id}` | `edit-post` |
| `DELETE /api/v1/blog/posts/{id}` | `delete-post` |
| `POST /api/v1/blog/categories` | `create-blog-category` |
| `PUT /api/v1/blog/categories/{id}` | `edit-blog-category` |
| `DELETE /api/v1/blog/categories/{id}` | `delete-blog-category` |

## Project Structure

```
inverys-cms/
├── app/
│   ├── Events/               # PageCreated, MediaUploaded, etc.
│   ├── Helpers/               # HtmlHelper, PageRouteHelper, ThemeHelper
│   ├── Http/Controllers/
│   │   ├── Admin/             # Backend CRUD controllers
│   │   └── Api/V1/            # REST API controllers
│   ├── Models/                # Eloquent models
│   └── Services/              # ThemeManager, BlockRenderer, BlockRegistry
├── config/cms.php             # CMS version, paths
├── database/migrations/       # All migrations
├── docs/                      # User guide, theme & plugin development guides
├── lang/en/                   # English UI strings (German: plugins/language-de)
├── plugins/                   # Plugin directory
│   ├── activity-log/          # Activity log plugin (CRUD tracking, auth events, diffs)
│   ├── blog/                  # Blog plugin (posts, categories, RSS, API)
│   ├── captcha/               # Captcha plugin (multiple providers, form field integration)
│   ├── cookie-consent/        # Cookie consent plugin (GDPR, banner, services)
│   ├── form-builder/          # Form builder plugin (drag-and-drop editor, submissions)
│   ├── gallery/               # Gallery plugin (image albums, page builder block)
│   ├── language-de/           # German language pack (UI + demo content)
│   ├── mail-providers/        # Mail providers plugin (SMTP, OAuth2, API transports)
│   └── multilingual/          # Multilingual content (URL prefix, fallback, translations)
├── resources/
│   ├── js/backend/            # Page builder, tree, Alpine components
│   ├── css/{backend,frontend}/ # Tailwind v4 stylesheets (tokens, components)
│   └── views/
│       ├── backend/           # Admin panel views
│       └── frontend/          # Public-facing views & block partials
├── routes/
│   ├── web.php                # Web routes
│   ├── api.php                # API routes
│   └── auth.php               # Authentication routes
└── themes/                    # Theme directory
    ├── default/               # Default theme
    └── modern/                # Modern theme with custom layouts
```

## Configuration

Key settings in `.env`:

```ini
APP_NAME="Inverys CMS"
APP_URL=http://localhost
APP_LOCALE=en              # en, or the code of an installed language pack (e.g. de)

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=inverys_cms
DB_USERNAME=root
DB_PASSWORD=

TRUSTED_HOSTS=             # further host names besides the APP_URL host
CMS_CODE_EDITING=true      # false: no theme file editor, uploads or updates on this server
CMS_REVISION_SESSION_MINUTES=10  # saves within this time form one revision (0 = every save)
CMS_UPDATE_TOKEN=          # GitHub token, only for a private update repository
```

> The active theme is **not** configured via `.env` — select it in the admin UI (**Extensions → Themes**); it is stored in the database. See the [Security Hardening Guide](docs/SECURITY-HARDENING.md) for `TRUSTED_PROXIES` and the production checklist.

## Testing

```bash
php artisan test                              # Run all tests
php artisan test --testsuite=Unit             # Unit tests only
php artisan test --testsuite=Feature          # Feature tests only
php artisan test --filter=PageControllerTest  # Specific test class
```

PHPUnit with SQLite in-memory. Full documentation including factories, base test class helpers, and examples for writing new tests: **[Testing Guide](docs/TESTING.md)**

## License

MIT
