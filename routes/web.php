<?php

use App\Helpers\PageRouteHelper;
use App\Http\Controllers\Admin\AdminSearchController;
use App\Http\Controllers\Admin\ApiTokenController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MarketplaceController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PageCategoryController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RevisionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SearchSettingController;
use App\Http\Controllers\Admin\SecuritySettingController;
use App\Http\Controllers\Admin\SeoSettingController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemInfoController;
use App\Http\Controllers\Admin\ThemeController;
use App\Http\Controllers\Admin\ThemeEditorController;
use App\Http\Controllers\Admin\TranslationCopyController;
use App\Http\Controllers\Admin\TrashController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\Auth\TwoFactorSetupController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\ExtensionAssetController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProtectedAssetController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SecurityTxtController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\InjectPluginHooks;
use App\Http\Middleware\SetSecurityHeaders;
use App\Support\Sbom\Inventory;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

// Installer routes (only accessible when not yet installed, CSRF exempt)
Route::middleware('install.guard')
    ->withoutMiddleware([
        // Laravel 13's CSRF/origin middleware — the web group now carries
        // PreventRequestForgery (ValidateCsrfToken is only a BC subclass of it),
        // so we must exclude the actual class to keep the installer AJAX exempt.
        PreventRequestForgery::class,
        'ensure.active', 'set.locale', 'inject.hooks',
    ])
    ->group(function () {
        Route::get('/install', [InstallController::class, 'index'])->name('install.index');
        Route::get('/install/state', [InstallController::class, 'state'])->name('install.state');
        Route::post('/install/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
        Route::post('/install/save', [InstallController::class, 'save'])->name('install.save');
        Route::post('/install/step', [InstallController::class, 'runStep'])->name('install.step');
    });

// Frontend routes
Route::get('/', [PageController::class, 'show'])->name('home');
// Throttled: every search scans all content
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1')->name('search');

// Sitemap and robots.txt — before the page catch-all route (both names are
// additionally reserved in PageRouteHelper::reservedSlugs())
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
// Sitemap per language (multilingual) — file name with a dot, so it never gets a language prefix
Route::get('/sitemap-{locale}.xml', [SitemapController::class, 'locale'])
    ->where('locale', PageRouteHelper::SITEMAP_LOCALE_PATTERN)
    ->name('sitemap.locale');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Reporting channel for vulnerabilities (RFC 9116) — only with SECURITY_TXT_CONTACT
Route::get('/.well-known/security.txt', SecurityTxtController::class)->name('security-txt');

// Toggle light/dark — also for visitors who are not logged in
Route::post('/theme', [AppearanceController::class, 'toggle'])->name('theme.toggle');

// Email change: links from the emails (signed, also on another device without login)
Route::middleware(['signed', 'throttle:10,1'])->prefix('email-change')->group(function () {
    Route::get('/confirm/{user}/{nonce}', [EmailChangeController::class, 'showConfirm'])->name('email-change.confirm');
    Route::post('/confirm/{user}/{nonce}', [EmailChangeController::class, 'confirm']);
    Route::get('/revert/{user}', [EmailChangeController::class, 'showRevert'])->name('email-change.revert');
    Route::post('/revert/{user}', [EmailChangeController::class, 'revert']);
});

// Static files of themes and plugins (the package's assets/)
Route::get('/ext-assets/{type}/{slug}/{path}', [ExtensionAssetController::class, 'show'])
    ->whereIn('type', ['theme', 'plugin'])
    ->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?')
    ->where('path', '.*')
    // Own headers for files (SVG with strict CSP) — not those of the pages
    ->withoutMiddleware([SetSecurityHeaders::class, InjectPluginHooks::class])
    ->name('extension.asset');

Route::middleware('auth')->group(function () {
    // Protected assets route
    Route::get('/protected-assets/{path}', [ProtectedAssetController::class, 'show'])
        ->where('path', '.*')
        ->name('protected.assets');

    // Avatar specific route - must be defined before /profile/{user} to avoid conflict
    Route::delete('/profile/avatar/delete', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
    Route::post('/profile/email/cancel', [EmailChangeController::class, 'cancel'])->name('email-change.cancel');

    // Two-Factor Setup routes
    // Throttled: toggle/disable-totp/regenerate-codes check 6-digit codes —
    // without a limit they could be brute-forced (unlike the challenge).
    Route::prefix('profile/two-factor')->middleware('throttle:10,1')->group(function () {
        Route::post('/toggle', [TwoFactorSetupController::class, 'toggle'])->name('two-factor.toggle');
        Route::post('/enable-totp', [TwoFactorSetupController::class, 'enableTotp'])->name('two-factor.enable-totp');
        Route::post('/confirm-totp', [TwoFactorSetupController::class, 'confirmTotp'])->name('two-factor.confirm-totp');
        Route::post('/disable-totp', [TwoFactorSetupController::class, 'disableTotp'])->name('two-factor.disable-totp');
        Route::post('/regenerate-codes', [TwoFactorSetupController::class, 'regenerateRecoveryCodes'])->name('two-factor.regenerate-codes');
        Route::post('/send-code', [TwoFactorSetupController::class, 'sendCode'])->name('two-factor.send-code');
    });

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    // Throttled: both check the current password — otherwise brute-forceable
    // with a hijacked session
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:10,1')->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:6,1')->name('profile.destroy');
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme.update');

    // Backend routes
    Route::prefix('backend')->middleware(['permission:access-dashboard'])->group(function () {
        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])
            ->metadata(['title' => 'translation.navigation.dashboard'])
            ->name('dashboard');
        Route::put('/dashboard/layout', [DashboardController::class, 'saveLayout'])->name('dashboard.layout.save');

        // Revisions of every registered content type (pages, blog posts, plugin content)
        Route::prefix('revisions/{type}/{id}')->where(['type' => '[a-z0-9-]+'])->group(function () {
            Route::get('/', [RevisionController::class, 'index'])->name('backend.revisions.index');
            Route::get('/{revision}', [RevisionController::class, 'show'])->name('backend.revisions.show');
            Route::get('/{revision}/preview', [RevisionController::class, 'preview'])->name('backend.revisions.preview');
            Route::post('/{revision}/restore', [RevisionController::class, 'restore'])->name('backend.revisions.restore');
            Route::delete('/{revision}', [RevisionController::class, 'destroy'])->name('backend.revisions.destroy');
        });

        // Quick search (Ctrl/⌘ + K): pages, content and settings, including from plugins
        Route::get('/search', AdminSearchController::class)->middleware('throttle:120,1')->name('backend.search');
        Route::delete('/dashboard/layout', [DashboardController::class, 'resetLayout'])->name('dashboard.layout.reset');

        // Copy a translation from another language (signed URL from the editor)
        Route::post('translations/copy', [TranslationCopyController::class, 'store'])
            ->middleware('signed:relative')
            ->name('backend.translations.copy');

        // Backend Profile route
        Route::get('/profile', [ProfileController::class, 'edit'])
            ->metadata(['title' => 'translation.profile'])
            ->name('backend.profile');

        // Settings routes
        Route::get('settings/search', [SearchSettingController::class, 'search'])
            ->metadata(['title' => 'translation.navigation.search'])
            ->name('backend.settings.search');
        Route::post('settings/search', [SearchSettingController::class, 'saveSearch'])->name('backend.settings.search.save');
        Route::get('settings/security', [SecuritySettingController::class, 'security'])
            ->metadata(['title' => 'translation.navigation.security'])
            ->name('backend.settings.security');
        Route::post('settings/security', [SecuritySettingController::class, 'saveSecurity'])->name('backend.settings.security.save');
        Route::get('settings/seo', [SeoSettingController::class, 'edit'])
            ->metadata(['title' => 'translation.navigation.seo'])
            ->name('backend.settings.seo');
        Route::post('settings/seo', [SeoSettingController::class, 'save'])->name('backend.settings.seo.save');
        // Redirects from old addresses (manual and automatic on slug changes)
        Route::get('settings/seo/redirects', [RedirectController::class, 'index'])
            ->metadata(['title' => 'translation.redirects.title'])
            ->name('backend.settings.seo.redirects');
        Route::post('settings/seo/redirects', [RedirectController::class, 'store'])->name('backend.settings.seo.redirects.store');
        Route::put('settings/seo/redirects/{redirect}', [RedirectController::class, 'update'])->name('backend.settings.seo.redirects.update');
        Route::delete('settings/seo/redirects/{redirect}', [RedirectController::class, 'destroy'])->name('backend.settings.seo.redirects.destroy');
        Route::resource('settings', SettingController::class, [
            'as' => 'backend',
            'only' => ['index', 'edit', 'update'],
        ])->metadata(['title' => 'translation.navigation.settings']);

        // Roles routes
        Route::resource('roles', RoleController::class, [
            'as' => 'backend',
        ])->metadata(['title' => 'translation.navigation.roles']);

        // User management routes
        Route::resource('users', UserController::class, [
            'as' => 'backend',
        ])->metadata(['title' => 'translation.navigation.users']);
        Route::delete('users/{user}/avatar/delete', [UserController::class, 'deleteAvatar'])->name('backend.users.avatar.delete');

        // Permissions routes
        Route::resource('permissions', PermissionController::class, [
            'as' => 'backend',
        ])->metadata(['title' => 'translation.navigation.permissions']);

        // Log management routes
        Route::get('logs', [LogController::class, 'index'])
            ->metadata(['title' => 'translation.navigation.logs'])
            ->name('backend.logs.index');
        Route::get('logs/{filename}', [LogController::class, 'show'])->where('filename', '[\w\-\.]+\.log')
            ->metadata(['title' => 'translation.navigation.logs'])
            ->name('backend.logs.show');
        Route::post('logs/{filename}/clear', [LogController::class, 'clear'])->where('filename', '[\w\-\.]+\.log')->name('backend.logs.clear');
        Route::get('logs/{filename}/download', [LogController::class, 'download'])->where('filename', '[\w\-\.]+\.log')->name('backend.logs.download');

        // System info & cache
        Route::get('system-info', [SystemInfoController::class, 'show'])
            ->metadata(['title' => 'translation.navigation.system_info'])
            ->name('backend.system.info');
        Route::post('system-info/clear-cache', [SystemInfoController::class, 'clearCache'])->name('backend.system.clear-cache');
        // Components (SBOM): list, installation SBOM and bundled release SBOMs
        Route::get('system-info/components', [SystemInfoController::class, 'components'])
            ->metadata(['title' => 'translation.system.sbom.title'])
            ->name('backend.system.components');
        Route::get('system-info/components/download', [SystemInfoController::class, 'downloadSbom'])->name('backend.system.sbom.download');
        Route::get('system-info/components/release/{file}/download', [SystemInfoController::class, 'downloadRelease'])
            ->whereIn('file', array_keys(Inventory::RELEASE_FILES))
            ->name('backend.system.sbom.release');

        // Pages routes
        Route::prefix('pages')->metadata(['title' => 'translation.navigation.pages'])->group(function () {
            Route::get('/', [AdminPageController::class, 'index'])->name('backend.pages.index');
            Route::get('/tree', [AdminPageController::class, 'tree'])->name('backend.pages.tree');
            Route::post('/tree', [AdminPageController::class, 'updateTree'])->name('backend.pages.update-tree'); // This matches our JS endpoint
            Route::get('/create', [AdminPageController::class, 'create'])->name('backend.pages.create');
            Route::post('/', [AdminPageController::class, 'store'])->name('backend.pages.store');
            Route::get('/{page}/edit', [AdminPageController::class, 'edit'])->name('backend.pages.edit');
            Route::put('/{page}', [AdminPageController::class, 'update'])->name('backend.pages.update');
            Route::delete('/{page}', [AdminPageController::class, 'destroy'])->name('backend.pages.destroy');
            Route::post('/preview-blocks', [AdminPageController::class, 'previewBlocks'])->name('backend.pages.preview-blocks');
            Route::post('/{page}/save-blocks', [AdminPageController::class, 'saveBlocks'])->name('backend.pages.save-blocks');
            Route::get('/{page}/canvas', [AdminPageController::class, 'canvas'])->name('backend.pages.canvas');
            Route::post('/restore/{id}', [AdminPageController::class, 'restore'])->name('backend.pages.restore');

            // Page revisions (the same as under backend/revisions/page/{id})
            Route::get('/{id}/revisions', [RevisionController::class, 'index'])->defaults('type', 'page')->name('backend.pages.revisions.index');
            Route::get('/{id}/revisions/{revision}', [RevisionController::class, 'show'])->defaults('type', 'page')->name('backend.pages.revisions.show');
            Route::get('/{id}/revisions/{revision}/preview', [RevisionController::class, 'preview'])->defaults('type', 'page')->name('backend.pages.revisions.preview');
            Route::post('/{id}/revisions/{revision}/restore', [RevisionController::class, 'restore'])->defaults('type', 'page')->name('backend.pages.revisions.restore');
            Route::delete('/{id}/revisions/{revision}', [RevisionController::class, 'destroy'])->defaults('type', 'page')->name('backend.pages.revisions.destroy');
        });
        // Page Categories routes
        Route::resource('page-categories', PageCategoryController::class, [
            'as' => 'backend',
            'except' => ['show'],
        ])->metadata(['title' => 'translation.pages.page_categories']);

        // Theme routes
        Route::prefix('themes')->metadata(['title' => 'translation.navigation.themes'])->group(function () {
            Route::get('/', [ThemeController::class, 'index'])->name('backend.themes.index');
            Route::post('/upload', [ThemeController::class, 'upload'])->name('backend.themes.upload');
            Route::post('/activate', [ThemeController::class, 'activate'])->name('backend.themes.activate');
            // Marketplace: themes from public GitHub repositories (App\Services\Updates\Marketplace)
            Route::get('/marketplace', [MarketplaceController::class, 'index'])->defaults('type', 'theme')->name('backend.themes.marketplace');
            Route::post('/marketplace/refresh', [MarketplaceController::class, 'refresh'])->defaults('type', 'theme')->name('backend.themes.marketplace.refresh');
            Route::post('/marketplace/install', [MarketplaceController::class, 'install'])->defaults('type', 'theme')->name('backend.themes.marketplace.install');
            Route::post('/marketplace/update', [MarketplaceController::class, 'update'])->defaults('type', 'theme')->name('backend.themes.marketplace.update');
            Route::post('/marketplace/sources', [MarketplaceController::class, 'saveSources'])->defaults('type', 'theme')->name('backend.themes.marketplace.sources');
            Route::get('/{slug}/customize', [ThemeController::class, 'customize'])->name('backend.themes.customize');
            Route::post('/{slug}/customize', [ThemeController::class, 'saveCustomization'])->name('backend.themes.save-customization');
            Route::post('/{slug}/region/{region}', [ThemeController::class, 'saveRegion'])->name('backend.themes.save-region');
            Route::post('/{slug}/update', [ThemeController::class, 'update'])->name('backend.themes.update')->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?');
            Route::post('/{slug}/restore', [ThemeController::class, 'restore'])->name('backend.themes.restore')->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?');
            Route::delete('/{slug}', [ThemeController::class, 'destroy'])->name('backend.themes.destroy');

            // Theme file editor
            // Before editing code: confirm the password again (valid for a few hours)
            Route::get('/{slug}/editor', [ThemeEditorController::class, 'index'])->name('backend.themes.editor');
            Route::get('/{slug}/editor/file', [ThemeEditorController::class, 'loadFile'])->name('backend.themes.editor.load');
            Route::put('/{slug}/editor/file', [ThemeEditorController::class, 'saveFile'])->name('backend.themes.editor.save');
            Route::post('/{slug}/editor/reset', [ThemeEditorController::class, 'resetFile'])->name('backend.themes.editor.reset');
        });

        // Plugin routes
        Route::prefix('plugins')->metadata(['title' => 'translation.navigation.plugins'])->group(function () {
            Route::get('/', [PluginController::class, 'index'])->name('backend.plugins.index');
            Route::post('/upload', [PluginController::class, 'upload'])->name('backend.plugins.upload');
            // Marketplace: plugins from public GitHub repositories; like the list, behind a password confirmation
            Route::get('/marketplace', [MarketplaceController::class, 'index'])->defaults('type', 'plugin')->middleware('password.confirm')->name('backend.plugins.marketplace');
            Route::post('/marketplace/refresh', [MarketplaceController::class, 'refresh'])->defaults('type', 'plugin')->name('backend.plugins.marketplace.refresh');
            Route::post('/marketplace/install', [MarketplaceController::class, 'install'])->defaults('type', 'plugin')->name('backend.plugins.marketplace.install');
            Route::post('/marketplace/update', [MarketplaceController::class, 'update'])->defaults('type', 'plugin')->name('backend.plugins.marketplace.update');
            Route::post('/marketplace/sources', [MarketplaceController::class, 'saveSources'])->defaults('type', 'plugin')->name('backend.plugins.marketplace.sources');
            Route::post('/{slug}/enable', [PluginController::class, 'enable'])->name('backend.plugins.enable');
            Route::post('/{slug}/disable', [PluginController::class, 'disable'])->name('backend.plugins.disable');
            Route::post('/{slug}/update', [PluginController::class, 'update'])->name('backend.plugins.update')->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?');
            Route::post('/{slug}/restore', [PluginController::class, 'restore'])->name('backend.plugins.restore')->where('slug', '[a-z0-9]([a-z0-9-]*[a-z0-9])?');
            Route::delete('/{slug}', [PluginController::class, 'destroy'])->name('backend.plugins.destroy');
        });

        // Updates of the CMS (plugins and themes are updated in their own lists)
        Route::prefix('updates')->metadata(['title' => 'translation.navigation.updates'])->group(function () {
            Route::get('/', [UpdateController::class, 'index'])->name('backend.updates.index');
            Route::post('/check', [UpdateController::class, 'check'])->name('backend.updates.check');
            Route::post('/core/update', [UpdateController::class, 'update'])->name('backend.updates.core.update');
            Route::post('/core/upload', [UpdateController::class, 'upload'])->name('backend.updates.core.upload');
            // Step 2 of a CMS update: runs with the new code, key from step 1
            Route::get('/core/finish', [UpdateController::class, 'finish'])->name('backend.updates.core.finish');
            Route::post('/core/restore', [UpdateController::class, 'restore'])->name('backend.updates.core.restore');
        });

        // API Token routes
        Route::prefix('api-tokens')->metadata(['title' => 'translation.navigation.api_tokens'])->group(function () {
            Route::get('/', [ApiTokenController::class, 'index'])->name('backend.api-tokens.index');
            Route::post('/', [ApiTokenController::class, 'store'])->name('backend.api-tokens.store');
            Route::delete('/{apiToken}', [ApiTokenController::class, 'destroy'])->name('backend.api-tokens.destroy');
        });

        // Notification routes
        Route::prefix('notifications')->metadata(['title' => 'translation.navigation.notifications'])->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('backend.notifications.index');
            Route::get('/data', [NotificationController::class, 'data'])->name('backend.notifications.data');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('backend.notifications.unread-count');
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('backend.notifications.mark-read');
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('backend.notifications.mark-all-read');
            Route::delete('/all', [NotificationController::class, 'destroyAll'])->name('backend.notifications.destroy-all');
            Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('backend.notifications.destroy');
            Route::get('/settings', [NotificationController::class, 'settings'])->name('backend.notifications.settings');
            Route::post('/settings', [NotificationController::class, 'saveSettings'])->name('backend.notifications.settings.save');
        });

        // Media routes
        Route::prefix('media')->metadata(['title' => 'translation.navigation.media'])->group(function () {
            Route::get('/', [MediaController::class, 'index'])->name('backend.media.index');
            Route::get('/create', [MediaController::class, 'create'])->name('backend.media.create');
            Route::post('/', [MediaController::class, 'store'])->name('backend.media.store');
            Route::get('/{media}', [MediaController::class, 'show'])->name('backend.media.show');
            Route::get('/{media}/edit', [MediaController::class, 'edit'])->name('backend.media.edit');
            Route::put('/{media}', [MediaController::class, 'update'])->name('backend.media.update');
            Route::delete('/{media}', [MediaController::class, 'destroy'])->name('backend.media.destroy');
            Route::post('/upload', [MediaController::class, 'ajaxUpload'])->name('backend.media.ajax-upload');
            Route::get('/api/list', [MediaController::class, 'getMedia'])->name('backend.media.api.list');
        });

        // Trash routes
        Route::get('/trash', [TrashController::class, 'index'])
            ->metadata(['title' => 'translation.trash'])
            ->name('backend.trash.index');
        Route::post('/trash/{type}/{id}/restore', [TrashController::class, 'restore'])->name('backend.trash.restore');
        Route::delete('/trash/{type}/{id}', [TrashController::class, 'forceDelete'])->name('backend.trash.forceDelete');
    });
});

require __DIR__.'/auth.php';

// Frontend page catch-all — MUST be last to avoid shadowing backend/auth routes
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', PageRouteHelper::slugRegex())
    ->name('page.show');
