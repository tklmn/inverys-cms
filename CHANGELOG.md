# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

First public release of Inverys CMS — a modular content management system built on Laravel 13 and PHP 8.4, with a block-based page builder, swappable themes, a plugin architecture and a REST API.

### Added

#### Content

- **Pages** in a drag-and-drop page tree with parent/child hierarchy, page categories, templates, draft and online status and automatic subpage lists. Menu links to the own site are stored as paths, so they survive a domain change.
- **Revisions** for pages and blog posts: one revision per editing session (`CMS_REVISION_SESSION_MINUTES`), a separate history per language, block-level comparison with the current or the next version, preview of an old version, restore that keeps the current state, and a configurable retention limit.
- **Media library** with thumbnails, alt texts, search, a global picker and a warning before an image in use is deleted.
- **Trash** for pages, users, media, roles, permissions, plugins, theme settings and plugin content — restore or delete permanently.
- **Site search** with extensible providers, per-provider field switches and pagination.
- **Quick search** in the admin area (<kbd>Ctrl</kbd>/<kbd>⌘</kbd>+<kbd>K</kbd>): menu items, settings pages by topic, pages, media, users and plugin content — limited to what the user may open.
- **Demo content** on request in the installer (feature pages with illustrations, blog posts, page categories), renewable with `php artisan cms:demo-refresh`; without it a site starts with a home page and a footer.

#### Page builder

- **Full-screen page editor** in three columns: the blocks as a tree, a live preview of the page in the active theme (desktop, tablet and mobile width), and a sidebar with block settings, page settings and revisions.
- **23 core block types** for layout, hero, stats, pricing, logos, buttons, content and interactive elements, with nested containers. Design options are picked from lists (background, spacing, alignment, icons) instead of CSS classes.
- **Editing in the preview:** select blocks, drag them to a new place, edit texts directly, and move, duplicate or delete them from a toolbar.
- **Auto-save, undo and redo** and keyboard shortcuts; an *Add block* dialog with search, categories and recently used blocks; a *New page* dialog with starting layouts (blank, text page, landing page).
- TinyMCE rich-text editing; plugins add their own block types.

#### Languages

- **English and German user interface:** the German language pack brings the complete UI and German demo content; further languages come as language packs.
- **Multilingual content** with the Multilingual plugin: pages, posts, menus, forms, albums, media and settings per language with their own addresses (`/de/…`), fallback to the original, translation status in every list, `hreflang` alternates and one sitemap per language.
- Dates and times follow the interface language. Language, time zone, date format and light/dark mode can be set per user and otherwise follow the site defaults.

#### Website and themes

- **Themes:** *Default* and *Modern* (both light and dark) are bundled, a starter theme is available via Composer. Settings come from a JSON schema with a customizer (colours, fonts, corners, header, footer), editable regions, custom CSS and a theme file editor.
- Themes and plugins install via ZIP upload or Composer; themes can override plugin views.
- **Lean page output** for every theme and plugin: minified HTML with inline CSS and JavaScript, an icon sprite, a copyright notice in the page source, and email addresses hidden from spam bots.
- **Responsive navigation:** a hamburger menu on mobile; on desktop, entries that do not fit move into a *More* dropdown.
- **SEO:** meta title, description, robots, Open Graph and canonical URL per page; an XML sitemap; a configurable `robots.txt` (automatic, custom or off); blocking search engines also sends `X-Robots-Tag: noindex`.
- Standalone error pages (403, 404, 419, 429, 500, 503).

#### Administration

- **Backend design system** on Tailwind CSS v4 with tokens for light and dark mode, Lucide icons and lists with a detail panel.
- **Dashboard** with a row-based layout builder, drag-and-drop widgets, widget widths, spacers and a layout per user, including a *System & Security* widget with health notes.
- **Users, roles and permissions:** four default roles (admin, editor, user, blocked), custom roles and granular permissions for every feature; users can only grant permissions they hold themselves.
- **Notifications** for HTTP errors, failed logins, changed log files and available updates.
- **System information** with log viewer and a *Components (SBOM)* tab that lists the installed packages, plugins, themes and PHP and downloads a CycloneDX 1.5 SBOM of the installation.
- **Account area** at `/profile` for users without backend access; email changes take effect only after a confirmation link, and the old address gets an undo link.

#### Installation and updates

- **Web installer:** checks the requirements, creates `.env` and the application key, configures the database (MySQL, MariaDB, PostgreSQL or SQLite), sets site name, language and admin account, shows its progress step by step and resumes after a reload. It stays locked once the site is installed.
- **Release package** `inverys-cms-<version>.zip` with `vendor/` and the built assets for shared hosting; after an FTP upload the CMS recreates missing `storage/` folders and the `public/storage` link itself.
- **CMS updates from the backend** (System → Updates): download with SHA-256 check or upload of the package, database and file backup, maintenance mode, migrations with rollback, restore of the previous version and an emergency recovery page.
- **Plugin and theme updates** from their lists for packages that name a GitHub repository, with backups of the previous versions; a daily update check notifies about new versions (`cms:check-updates`).

#### Security

- Strict nonce-based Content-Security-Policy, security headers (HSTS on HTTPS) and host header validation (`TRUSTED_HOSTS`, `TRUSTED_PROXIES`).
- XSS and CSS sanitising (HTMLPurifier), CSRF protection, permission middleware, and rate limits with account lockout for login, two-factor codes, password checks, password reset, forms and the API.
- Two-factor authentication with email codes or an authenticator app (TOTP) and recovery codes, on for all users by default.
- Admin-configurable password policy, registration switch, session timeout, data retention and `/.well-known/security.txt` (RFC 9116).
- Secrets in settings are encrypted with `APP_KEY` and never rendered into forms; uploads are stored under their validated extension and served with a sandbox CSP; the root `.htaccess` serves only `public/`.
- Code on the server (theme file editor, plugin and theme uploads, CMS updates) is reserved for the admin role and can be switched off with `CMS_CODE_EDITING=false`.
- CycloneDX SBOMs for Composer and npm in `sbom/`, a security policy with coordinated disclosure (`SECURITY.md`), a hardening guide, a vulnerability handling process and an EU Cyber Resilience Act readiness checklist.

#### REST API

- Versioned JSON API under `/api/v1` with bearer tokens (scopes and expiry), two-factor login, rate limiting and permission-gated endpoints for pages, media, users, settings, themes, plugins, block types and the blog.
- Opt-in [JSON:API](https://jsonapi.org/) representation via `Accept: application/vnd.api+json`.

#### Plugins and development

- **Nine bundled plugins:** Activity Log, Blog, Captcha, Cookie Consent, Form Builder, Gallery, German language pack, Mail Providers and Multilingual; an announcement bar is available as a Composer plugin.
- **Plugin SDK** with 13 contracts (`SettingRepository`, `PageManager`, `MediaManager`, `PermissionManager`, `RouteRegistrar`, `ExtensionRegistrar`, `CmsUserProvider`, `SearchProvider`, `AdminSearchProvider`, `MediaUsageChecker`, `SitemapProvider`, `SitemapLocalizer`, `ContentTranslator`), lifecycle hooks, settings pages, sidebar entries, dashboard widgets, block types, revisions for plugin content, head and body hooks and static files under `/ext-assets/` — plugin code never imports core model classes.
- **Quality tooling** in `bin/`: browser check (Playwright), translation, hard-coded text and German text checks, view audits and lint scripts, and a release script with test, dependency audit and SBOM gates.
- **Documentation:** user guide, theme and plugin development guides, REST API, testing, design system and security guides in `docs/`.
