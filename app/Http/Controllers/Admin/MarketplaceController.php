<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SettingRepository;
use App\Http\Controllers\Concerns\HandlesPackageUpdates;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Models\Setting;
use App\Models\User;
use App\Services\Updates\Marketplace;
use App\Services\Updates\UpdateException;
use App\Support\CodeAccess;
use App\Support\RemoteFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Extensions → Themes / Plugins → Marketplace: browse the catalogs
 * (App\Services\Updates\Marketplace), install and update from them.
 *
 * The routes pass the type ("theme" or "plugin"). Permissions:
 *   - browse: manage-themes / manage-plugins, or the install permission
 *   - install, update: install-themes / install-plugins — any role can get
 *     them; the server must allow code changes (CMS_CODE_EDITING) and the
 *     password is confirmed again
 *   - sources: manage-marketplace, administrators only — a source decides
 *     which code can be installed at all
 */
class MarketplaceController extends Controller
{
    use HandlesPackageUpdates;

    public function index(Request $request, Marketplace $marketplace, string $type): View
    {
        $this->authorizeBrowse($request, $type);

        $catalog = Marketplace::enabled() && Marketplace::sources() !== []
            ? $marketplace->catalog($type)
            : ['items' => [], 'errors' => [], 'fetched_at' => null];

        return view('backend.marketplace.index', [
            'type' => $type,
            'items' => $marketplace->withState($type, $catalog['items']),
            'catalogErrors' => $catalog['errors'],
            'fetchedAt' => $catalog['fetched_at'],
            'enabled' => Marketplace::enabled(),
            'configuredSources' => Marketplace::configuredSources(),
            'addedSources' => Marketplace::addedSources(),
            'installBlocked' => self::installBlockedReason($request->user(), $type),
            'canManageSources' => self::canManageSources($request->user()),
        ]);
    }

    public function refresh(Request $request, Marketplace $marketplace, string $type): RedirectResponse
    {
        $this->authorizeBrowse($request, $type);
        abort_unless(Marketplace::enabled() && Marketplace::sources() !== [], 404);

        $errors = $marketplace->catalog($type, true)['errors'];

        return redirect()->route($this->listRoute($type))
            ->with($errors === [] ? 'success' : 'error', $errors === [] ? __('translation.marketplace.refreshed') : implode(' ', $errors));
    }

    public function install(Request $request, Marketplace $marketplace, string $type): RedirectResponse
    {
        $listRoute = $this->listRoute($type);
        if ($response = $this->guardInstall($request, $type)) {
            return $response;
        }

        $slug = $this->validatedSlug($request);
        @set_time_limit(300);

        try {
            $result = $marketplace->install($type, $slug);
        } catch (UpdateException $e) {
            return redirect()->route($listRoute)->withErrors(['slug' => $e->getMessage()]);
        }

        // Leftovers of an earlier removal would hide the package again
        if ($type === 'theme') {
            Setting::onlyTrashed()->where('key', 'like', "theme.{$result['slug']}.%")->forceDelete();

            return redirect()->route($listRoute)
                ->with('success', __('translation.marketplace.installed_theme', ['name' => $result['name'], 'version' => $result['version']]));
        }

        Plugin::onlyTrashed()->where('slug', $result['slug'])->forceDelete();

        // A plugin is switched on in the plugin list — whoever may do that lands there
        $user = $request->user();
        if ($user->hasPermission('manage-plugins') && $user->hasRole('admin')) {
            return redirect()->route('backend.plugins.index')
                ->with('success', __('translation.marketplace.installed_plugin', ['name' => $result['name'], 'version' => $result['version']]));
        }

        return redirect()->route($listRoute)
            ->with('success', __('translation.marketplace.installed_plugin_admin', ['name' => $result['name'], 'version' => $result['version']]));
    }

    public function update(Request $request, Marketplace $marketplace, string $type): RedirectResponse
    {
        $listRoute = $this->listRoute($type);
        if ($response = $this->guardInstall($request, $type)) {
            return $response;
        }

        $slug = $this->validatedSlug($request);
        @set_time_limit(300);

        try {
            $result = $marketplace->update($type, $slug);
        } catch (UpdateException $e) {
            return redirect()->route($listRoute)->withErrors(['slug' => $e->getMessage()]);
        }

        return redirect()->route($listRoute)
            ->with('success', __('translation.updates.updated', ['name' => $result['name'], 'version' => $result['version']]));
    }

    /** Further catalog addresses besides CMS_MARKETPLACE_SOURCES */
    public function saveSources(Request $request, SettingRepository $settings, string $type): RedirectResponse
    {
        $this->authorizeBrowse($request, $type);
        abort_unless(Marketplace::enabled(), 404);
        abort_unless(self::canManageSources($request->user()), 403);

        $listRoute = $this->listRoute($type);
        if ($response = $this->requireFreshPassword($request, $listRoute)) {
            return $response;
        }

        $request->validate(['sources' => ['nullable', 'string', 'max:4000']]);

        $entered = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $request->input('sources', '')))));
        $invalid = array_values(array_filter($entered, fn (string $url) => ! RemoteFile::validUrl($url)));
        if ($invalid !== []) {
            return redirect()->route($listRoute)
                ->withErrors(['sources' => __('translation.marketplace.error_address', ['url' => $invalid[0]])])
                ->withInput();
        }

        // Sources from the .env are listed already and not stored twice
        $added = array_values(array_diff(Marketplace::parseSources($entered), Marketplace::configuredSources()));
        $settings->set(Marketplace::SOURCES_SETTING, implode("\n", $added), 'string', 'Marketplace sources (catalog addresses)');

        return redirect()->route($listRoute)->with('success', __('translation.marketplace.sources_saved'));
    }

    /** Why the user cannot install here, or null */
    public static function installBlockedReason(?User $user, string $type): ?string
    {
        return match (true) {
            ! CodeAccess::enabled() => __('translation.code_access.disabled'),
            ! $user?->hasPermission($type === 'theme' ? 'install-themes' : 'install-plugins') => __('translation.marketplace.no_install_permission'),
            default => null,
        };
    }

    public static function canManageSources(?User $user): bool
    {
        return $user !== null && $user->hasPermission('manage-marketplace') && $user->hasRole('admin');
    }

    /** Install permission, code changes allowed, freshly confirmed password */
    private function guardInstall(Request $request, string $type): ?RedirectResponse
    {
        $this->authorizeBrowse($request, $type);
        abort_unless(Marketplace::enabled(), 404);
        abort_unless(self::installBlockedReason($request->user(), $type) === null, 403);

        return $this->requireFreshPassword($request, $this->listRoute($type));
    }

    private function validatedSlug(Request $request): string
    {
        return (string) $request->validate([
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/'],
        ])['slug'];
    }

    private function authorizeBrowse(Request $request, string $type): void
    {
        abort_unless(in_array($type, Marketplace::TYPES, true), 404);

        $user = $request->user();
        $allowed = $type === 'theme'
            ? $user?->hasPermission('manage-themes') || $user?->hasPermission('install-themes')
            : ($user?->hasPermission('manage-plugins') && $user->hasRole('admin')) || $user?->hasPermission('install-plugins');

        abort_unless((bool) $allowed, 403);
    }

    private function listRoute(string $type): string
    {
        return $type === 'theme' ? 'backend.themes.marketplace' : 'backend.plugins.marketplace';
    }
}
