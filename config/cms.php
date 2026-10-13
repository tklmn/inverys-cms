<?php

use App\Models\Media;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Plugin;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;

return [
    /*
     * Revisions: if the same user saves again within this many minutes,
     * no further revision is created (one per editing session). 0 = every change.
     */
    'revisions' => [
        'session_minutes' => (int) env('CMS_REVISION_SESSION_MINUTES', 10),
    ],

    'version' => '1.0.0',

    'active_theme' => 'default',

    'themes_path' => base_path('themes'),

    // Edit theme files and upload theme/plugin packages (App\Support\CodeAccess).
    // false: disabled in the backend — changes only via Git/deploy
    'code_editing' => env('CMS_CODE_EDITING', true),

    'plugins_path' => base_path('plugins'),

    // Updates (Backend → System → Updates, cms:check-updates): where the
    // versions come from. Plugins and themes name their own source
    // ("repository" in plugin.json / theme.json). A token is only needed
    // for private GitHub repositories.
    'updates' => [
        'repository' => env('CMS_UPDATE_REPOSITORY', 'tklmn/inverys-cms'),
        'token' => env('CMS_UPDATE_TOKEN', ''),
        // Number of earlier versions kept as backups per plugin/theme
        'keep_backups' => 3,
        'max_download_kb' => 50 * 1024,
        // The CMS itself: full package including vendor/ and build
        'max_core_kb' => 300 * 1024,
        // Number of earlier CMS versions (with database backup) that are kept
        'keep_core_backups' => 2,
    ],

    // Core Eloquent models that plugins relate to. Exposed here so plugin models
    // can define relationships (belongsTo/belongsToMany) without importing core
    // model classes directly — keeping the plugin layer decoupled from the core.
    'models' => [
        'user' => User::class,
        'media' => Media::class,
        'page' => Page::class,
    ],

    // Core models that expose loggable/observable behaviour. Plugins (e.g. the
    // activity-log plugin) observe this list instead of hard-coding core model
    // classes, so the core owns the set and the plugin stays decoupled.
    'observable_models' => [
        Page::class,
        User::class,
        Media::class,
        Role::class,
        Permission::class,
        Setting::class,
        Plugin::class,
    ],

    // Upload limits
    'max_upload_size' => 10 * 1024, // KB
    'max_avatar_size' => 2048, // KB
    'max_plugin_size' => 20 * 1024, // KB

    // Pagination
    'pagination' => [
        'pages' => 15,
        'media' => 20,
        'users' => 10,
        'posts' => 15,
    ],

    // API
    'api' => [
        'rate_limit' => 60,
        'token_expiry_minutes' => 60 * 24,
    ],

    // Settings of bundled packages whose text visitors see and that can be
    // translated per language (multilingual plugin). Plugins register their
    // own via ExtensionRegistrar::registerTranslatableSetting(); this list is
    // for packages that do not do that (yet).
    'translatable_settings' => [
        'announcement_bar.message' => ['label' => 'Announcement bar message', 'group' => 'Announcement Bar', 'type' => 'textarea'],
        'announcement_bar.link_text' => ['label' => 'Announcement bar link text', 'group' => 'Announcement Bar'],
    ],

    // Development aid: every text that has no translation in the active
    // (non-English) locale is written to storage/logs/missing-translations.log.
    // Open the pages in that language, then read the log.
    'log_missing_translations' => (bool) env('CMS_LOG_MISSING_TRANSLATIONS', false),

    // Host names the site answers to, in addition to the APP_URL host and its
    // subdomains (comma-separated, no scheme/port). Requests with any other
    // Host header are rejected with 400 — this stops Host header injection
    // (e.g. poisoned password-reset links). "*" disables the check.
    'trusted_hosts' => env('TRUSTED_HOSTS', ''),

    // Reverse proxies / load balancers whose X-Forwarded-* headers are trusted
    // (comma-separated IPs/CIDRs, or "*" for all — only if the app is not
    // reachable directly). Needed behind a TLS-terminating proxy so HTTPS is
    // detected (secure URLs, HSTS). Empty: no proxy is trusted.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    // /.well-known/security.txt (RFC 9116) — how to report a vulnerability in
    // THIS website. Served only when a contact is set (e-mail or https URL,
    // comma-separated). Expires: fixed date (YYYY-MM-DD) or, when empty,
    // always "expires_days" from now.
    'security_txt' => [
        'contact' => env('SECURITY_TXT_CONTACT', ''),
        'expires' => env('SECURITY_TXT_EXPIRES', ''),
        'expires_days' => 180,
        'policy' => env('SECURITY_TXT_POLICY', ''),
        'encryption' => env('SECURITY_TXT_ENCRYPTION', ''),
        'preferred_languages' => env('SECURITY_TXT_LANGUAGES', 'en, de'),
    ],
];
