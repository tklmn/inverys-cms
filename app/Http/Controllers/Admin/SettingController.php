<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\GeneralSettings;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Controller for managing general application settings in the admin backend.
 * Only users with the appropriate permissions can view or edit settings.
 */
#[Middleware('auth')]
#[Middleware('permission:show-settings', only: ['index'])]
#[Middleware('permission:edit-settings', only: ['edit', 'update'])]
class SettingController extends Controller
{
    /**
     * Display a listing of all settings.
     */
    public function index(Request $request): View
    {
        // Order of the registry, not that of the database
        $settings = $this->generalSettings()->get()
            ->sortBy(fn (Setting $setting) => array_search($setting->key, GeneralSettings::KEYS, true))
            ->values();

        return view('backend.settings.index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Only the general settings (App\Support\GeneralSettings). edit/update
     * go through here too — otherwise security, mail and plugin settings
     * (incl. captcha secrets) could be read or changed by ID, although they
     * have their own permissions and pages (e.g. turning off
     * security.two_factor_enabled without manage-security-settings).
     *
     * A fixed list instead of "everything except known prefixes": a
     * disabled plugin does not register its prefix, so its settings
     * showed up here — without any check.
     */
    protected function generalSettings(): Builder
    {
        return Setting::whereIn('key', GeneralSettings::KEYS);
    }

    /**
     * Show the form for editing a specific setting.
     */
    public function edit($id): View|RedirectResponse
    {
        $setting = $this->generalSettings()->findOrFail($id);

        // If the setting has its own page, it is only edited there
        if ($managed = GeneralSettings::managedBy($setting->key)) {
            return redirect()->route($managed['route'])
                ->with('status', __('translation.settings.managed_reason', ['page' => $managed['label']]));
        }

        return view('backend.settings.edit', [
            'setting' => $setting,
            'definition' => GeneralSettings::definition($setting->key),
            'options' => GeneralSettings::options($setting->key),
        ]);
    }

    /**
     * Update the specified setting in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $setting = $this->generalSettings()->findOrFail($id);

        // Blocked, not just hidden: the active theme, for example, would bypass
        // ThemeManager::activate() here (check whether the theme exists)
        if ($managed = GeneralSettings::managedBy($setting->key)) {
            abort(403, __('translation.settings.managed_reason', ['page' => $managed['label']]));
        }

        $validated = $request->validate([
            'value' => GeneralSettings::rules($setting->key),
        ]);

        $setting->value = (string) ($validated['value'] ?? '');
        $setting->save();

        // The default language is cached per request
        app(Locales::class)->flush();

        return redirect()->route('backend.settings.edit', $setting)->with('success', __('translation.saved'));
    }
}
