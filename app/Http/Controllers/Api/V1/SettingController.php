<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ExtensionRegistrar;
use App\Http\Controllers\Controller;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Support\GeneralSettings;
use App\Support\Locales;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Core setting key prefixes that are always hidden from the API.
     */
    protected const CORE_SENSITIVE_PREFIXES = [
        'mail.',
        'imap.',
        'notification.',
        'security.',
    ];

    /**
     * Where settings with their own page are changed via the API — the
     * generic endpoint does not write them (otherwise e.g. the active
     * theme would bypass ThemeManager::activate() and manage-themes).
     */
    protected const DEDICATED_ENDPOINTS = [
        'active_theme' => 'PUT /api/v1/themes/active',
    ];

    public function index()
    {
        $query = Setting::query();

        $pluginPrefixes = app(ExtensionRegistrar::class)->getSettingsPrefixes();
        foreach (array_merge(self::CORE_SENSITIVE_PREFIXES, $pluginPrefixes) as $prefix) {
            $query->where('key', 'not like', $prefix.'%');
        }
        $query->where('key', '!=', 'notification_email');

        // Never return encrypted values (secrets) — not even when the plugin
        // is disabled and its prefix is therefore not registered
        $query->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'encrypted'));

        return SettingResource::collection($query->get());
    }

    /**
     * Only the general settings (App\Support\GeneralSettings), with
     * the same validation rules as in the backend.
     */
    public function update(Request $request, string $key)
    {
        if (! GeneralSettings::has($key)) {
            abort(403, 'This setting cannot be updated via the API.');
        }

        if ($managed = GeneralSettings::managedBy($key)) {
            $hint = isset(self::DEDICATED_ENDPOINTS[$key])
                ? ' Use '.self::DEDICATED_ENDPOINTS[$key].' instead.'
                : ' It is managed on its own page in the backend.';

            abort(403, 'This setting cannot be updated via the settings API.'.$hint);
        }

        $setting = Setting::where('key', $key)->firstOrFail();

        $validated = $request->validate([
            'value' => GeneralSettings::rules($key),
        ]);

        $setting->update(['value' => (string) ($validated['value'] ?? '')]);

        app(Locales::class)->flush();

        return new SettingResource($setting);
    }
}
