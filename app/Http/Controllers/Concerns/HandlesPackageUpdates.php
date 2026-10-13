<?php

namespace App\Http\Controllers\Concerns;

use App\Services\Updates\PackageStore;
use App\Services\Updates\PackageUpdater;
use App\Services\Updates\UpdateException;
use App\Support\CodeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Updating and restoring plugins and themes directly from their lists
 * (Extensions → Plugins / Themes).
 *
 * This puts code on the server: admins only, only with CMS_CODE_EDITING=true,
 * with a freshly confirmed password.
 */
trait HandlesPackageUpdates
{
    protected function updatePackage(Request $request, PackageUpdater $updater, string $type, string $slug, string $listRoute): RedirectResponse
    {
        if ($response = $this->guardCodeChange($request, $listRoute)) {
            return $response;
        }

        @set_time_limit(300);

        try {
            $result = $updater->update($type, $slug);
        } catch (UpdateException $e) {
            return redirect()->route($listRoute)->withErrors(['slug' => $e->getMessage()]);
        }

        return redirect()->route($listRoute)
            ->with('success', __('translation.updates.updated', ['name' => $result['name'], 'version' => $result['to']]));
    }

    protected function restorePackage(Request $request, PackageUpdater $updater, string $type, string $slug, string $listRoute): RedirectResponse
    {
        if ($response = $this->guardCodeChange($request, $listRoute)) {
            return $response;
        }

        try {
            $result = $updater->restore($type, $slug);
        } catch (UpdateException $e) {
            return redirect()->route($listRoute)->withErrors(['slug' => $e->getMessage()]);
        }

        return redirect()->route($listRoute)
            ->with('success', __('translation.updates.restored', ['name' => $result['name'], 'version' => $result['version']]));
    }

    /**
     * A plugin update is only complete once a request has loaded the new
     * code — report the result, roll back failed ones.
     */
    protected function flashSettledUpdates(PackageStore $store): void
    {
        foreach ($store->settle() as $result) {
            if ($result['status'] === 'updated') {
                session()->now('success', __('translation.updates.updated', ['name' => $result['slug'], 'version' => $result['version']]));
            } else {
                session()->now('error', __('translation.updates.rolled_back', ['name' => $result['slug'], 'version' => $result['version'], 'error' => $result['error'] ?? '']));
            }
        }
    }

    /**
     * Admin, CMS_CODE_EDITING and a freshly confirmed password. If the
     * confirmation expires, the user returns to the list afterwards (a POST
     * cannot be replayed).
     */
    private function guardCodeChange(Request $request, string $listRoute): ?RedirectResponse
    {
        abort_unless(CodeAccess::allowed($request->user()), 403);

        return $this->requireFreshPassword($request, $listRoute);
    }

    /** Back to the password confirmation when it is older than auth.password_timeout */
    private function requireFreshPassword(Request $request, string $listRoute): ?RedirectResponse
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if (time() - $confirmedAt > (int) config('auth.password_timeout', 10800)) {
            $request->session()->put('url.intended', route($listRoute));

            return redirect()->route('password.confirm');
        }

        return null;
    }
}
