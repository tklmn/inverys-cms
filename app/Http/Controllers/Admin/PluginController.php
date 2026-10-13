<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesPackageUpdates;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\PackageInstaller;
use App\Services\PluginManager;
use App\Services\Updates\PackageStore;
use App\Services\Updates\PackageUpdater;
use App\Services\Updates\UpdateChecker;
use App\Support\CodeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

#[Middleware('permission:manage-plugins')]
// After the permission check: confirm the password again before managing
#[Middleware('password.confirm', only: ['index'])]
class PluginController extends Controller
{
    use HandlesPackageUpdates;

    public function index(Request $request, PluginManager $pluginManager, UpdateChecker $updates, PackageStore $store): View
    {
        $this->flashSettledUpdates($store);

        // Show only one kind (so far: language packs, e.g. from Languages → Manage language packs)
        $type = $request->query('type') === 'language' ? 'language' : null;

        $discovered = $pluginManager->all();
        $installed = Plugin::all()->keyBy('slug');
        $trashedSlugs = Plugin::onlyTrashed()->pluck('slug')->toArray();

        $plugins = [];
        foreach ($discovered as $slug => $manifest) {
            // Hide soft-deleted plugins from the listing
            if (in_array($slug, $trashedSlugs)) {
                continue;
            }

            if ($type !== null && ($manifest['type'] ?? null) !== $type) {
                continue;
            }

            $dbPlugin = $installed->get($slug);
            $plugins[] = [
                'slug' => $slug,
                // Manifests are in English; the language files translate name and description
                'name' => __($manifest['name'] ?? $slug),
                'version' => $manifest['version'] ?? '1.0.0',
                'description' => isset($manifest['description']) ? __($manifest['description']) : '',
                'author' => $manifest['author'] ?? __('Unknown'),
                'is_enabled' => $dbPlugin ? $dbPlugin->is_enabled : false,
                'installed_at' => $dbPlugin?->installed_at,
                'settings_route' => $manifest['settings_route'] ?? null,
                'is_system' => $manifest['is_system'] ?? $dbPlugin?->is_system ?? false,
                // Preview from the plugin's assets/ (served like its other static files)
                'screenshot' => $this->screenshotUrl($slug, $manifest),
                // Last backed-up state (before an update) — the way back
                'backup' => $store->backups('plugin', $slug)[0] ?? null,
            ];
        }

        return view('backend.plugins.index', [
            'plugins' => $plugins,
            'type' => $type,
            'hasLanguagePacks' => collect($discovered)->contains(fn ($manifest) => ($manifest['type'] ?? null) === 'language'),
            // Newer versions according to the last check (cms:check-updates)
            'updates' => $updates->availableFor('plugin'),
            'checkedAt' => $updates->checkedAt(),
        ]);
    }

    public function enable(Request $request, PluginManager $pluginManager, string $slug): RedirectResponse
    {
        if (! $this->isValidSlug($slug)) {
            return back()->withErrors(['slug' => __('Invalid plugin slug.')]);
        }

        if ($pluginManager->enable($slug)) {
            return redirect()->route('backend.plugins.index')
                ->with('success', __('Plugin enabled successfully.'));
        }

        return back()->withErrors(['slug' => __('Plugin not found.')]);
    }

    public function disable(Request $request, PluginManager $pluginManager, string $slug): RedirectResponse
    {
        if (! $this->isValidSlug($slug)) {
            return back()->withErrors(['slug' => __('Invalid plugin slug.')]);
        }

        if ($pluginManager->disable($slug)) {
            return redirect()->route('backend.plugins.index')
                ->with('success', __('Plugin disabled successfully.'));
        }

        return back()->withErrors(['slug' => __('Plugin not found.')]);
    }

    public function upload(Request $request, PackageInstaller $installer): RedirectResponse
    {
        abort_unless(CodeAccess::allowed($request->user()), 403);

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $result = $installer->installPlugin($request->file('package'));

        if (! $result['success']) {
            return back()->withErrors(['package' => $result['error']]);
        }

        // Remove a soft-deleted DB record so the plugin shows up again — only the
        // trashed one: an installed plugin keeps its record (enabled, version)
        Plugin::onlyTrashed()->where('slug', $result['slug'])->forceDelete();

        // The list confirms an update once the new code is loaded —
        // or restores the old state on failure
        return redirect()->route('backend.plugins.index')
            ->with('success', ! empty($result['updated'])
                ? __('translation.updates.uploaded_update', ['slug' => $result['slug'], 'version' => $result['version']])
                : __('Plugin ":slug" uploaded successfully. You can now enable it.', ['slug' => $result['slug']]));
    }

    /** To the latest version of the source ("repository" in plugin.json) */
    public function update(Request $request, PackageUpdater $updater, string $slug): RedirectResponse
    {
        return $this->updatePackage($request, $updater, 'plugin', $slug, 'backend.plugins.index');
    }

    /** Restore the state before the last update */
    public function restore(Request $request, PackageUpdater $updater, string $slug): RedirectResponse
    {
        return $this->restorePackage($request, $updater, 'plugin', $slug, 'backend.plugins.index');
    }

    public function destroy(Request $request, PluginManager $pluginManager, string $slug): RedirectResponse
    {
        // Deleting plugin code is a code change — blocked like uploading
        // with CMS_CODE_EDITING=false (see CodeAccess)
        abort_unless(CodeAccess::allowed($request->user()), 403);

        if (! $this->isValidSlug($slug)) {
            return redirect()->route('backend.plugins.index')
                ->withErrors(['slug' => __('Invalid plugin slug.')]);
        }

        // Only known plugins — the slug becomes a directory path below
        $manifest = $pluginManager->get($slug);
        if (! $manifest) {
            return redirect()->route('backend.plugins.index')
                ->withErrors(['slug' => __('Plugin not found.')]);
        }

        $plugin = Plugin::where('slug', $slug)->first();
        $isSystem = $plugin?->is_system || ($manifest['is_system'] ?? false);

        if ($isSystem) {
            return redirect()->route('backend.plugins.index')
                ->withErrors(['slug' => __('translation.plugin_protected')]);
        }

        // Run the plugin's uninstall hook (drops its tables/settings) before removing it,
        // so deleting a plugin doesn't leave orphaned data behind. Best-effort: only the
        // currently-loaded provider is available, and a failing hook must not block deletion.
        $provider = $pluginManager->getLoadedProviders()[$slug] ?? null;
        if ($provider) {
            try {
                $provider->uninstall();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Disable the plugin first if it is currently enabled
        $pluginManager->disable($slug);

        // Remove the database record
        Plugin::where('slug', $slug)->forceDelete();

        // Remove the plugin directory (uploaded plugins only, not vendor)
        $pluginDir = $this->deletableDirectory($manifest['path'] ?? null);
        if ($pluginDir !== null) {
            File::deleteDirectory($pluginDir);
        }

        return redirect()->route('backend.plugins.index')
            ->with('success', __('translation.plugin_deleted'));
    }

    /**
     * The plugin's directory if it sits directly under plugins/ — never
     * plugins/ itself and nothing outside (Composer packages in vendor/).
     */
    private function deletableDirectory(?string $path): ?string
    {
        $base = realpath((string) config('cms.plugins_path'));
        $dir = $path ? realpath($path) : false;

        if ($base === false || $dir === false || ! is_dir($dir)) {
            return null;
        }

        return dirname($dir) === $base ? $dir : null;
    }

    /** As on upload (PackageInstaller): lowercase letters, digits, hyphens */
    private function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', $slug);
    }

    /** URL of the preview named in plugin.json ("screenshot": "assets/…"), or null */
    private function screenshotUrl(string $slug, array $manifest): ?string
    {
        $path = $manifest['screenshot'] ?? null;
        if (! is_string($path) || ! str_starts_with($path, 'assets/') || ! is_file(($manifest['path'] ?? '').'/'.$path)) {
            return null;
        }

        return plugin_asset($slug, substr($path, strlen('assets/')));
    }
}
