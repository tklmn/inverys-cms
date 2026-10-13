<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Controller for managing search settings in the admin backend.
 */
#[Middleware('auth')]
#[Middleware('permission:manage-search-settings', only: ['search', 'saveSearch'])]
class SearchSettingController extends Controller
{
    /**
     * Show the search settings form.
     */
    public function search(): View
    {
        $providers = app()->bound('plugin.search_providers')
            ? app()->make('plugin.search_providers')
            : [];

        $searchSettings = [
            'search_min_length' => setting('search.min_length', '2'),
            'search_per_page' => setting('search.per_page', '10'),
            'search_guest_access' => setting('search.guest_access', 'on'),
        ];

        // Build provider data with per-provider field toggles
        $searchProviders = [];
        foreach ($providers as $provider) {
            $key = $provider->key();
            $fields = [];
            foreach ($provider->fields() as $fieldKey => $fieldConfig) {
                $settingKey = "search.{$key}.fields.{$fieldKey}";
                $fields[$fieldKey] = [
                    'label' => __($fieldConfig['label']),
                    'enabled' => setting($settingKey, $fieldConfig['default'] ?? 'on'),
                ];
            }
            $searchProviders[$key] = [
                'label' => __($provider->label()),
                'enabled' => setting("search.include_{$key}", 'on'),
                'fields' => $fields,
            ];
        }

        return view('backend.settings.search', compact('searchSettings', 'searchProviders'));
    }

    /**
     * Save search settings.
     */
    public function saveSearch(Request $request): RedirectResponse
    {
        $providers = app()->bound('plugin.search_providers')
            ? app()->make('plugin.search_providers')
            : [];

        // Build dynamic validation rules
        $rules = [
            'search_min_length' => 'required|integer|min:1|max:10',
            'search_per_page' => 'required|integer|min:5|max:100',
            'search_guest_access' => 'required|in:on,off',
        ];
        foreach ($providers as $provider) {
            $rules["search_include_{$provider->key()}"] = 'required|in:on,off';
            foreach ($provider->fields() as $fieldKey => $fieldConfig) {
                $rules["search_{$provider->key()}_field_{$fieldKey}"] = 'required|in:on,off';
            }
        }

        $request->validate($rules);

        // Build fields to save
        $fields = [
            'search.min_length' => $request->input('search_min_length'),
            'search.per_page' => $request->input('search_per_page'),
            'search.guest_access' => $request->input('search_guest_access'),
        ];
        foreach ($providers as $provider) {
            $key = $provider->key();
            $fields["search.include_{$key}"] = $request->input("search_include_{$key}");
            foreach ($provider->fields() as $fieldKey => $fieldConfig) {
                $fields["search.{$key}.fields.{$fieldKey}"] = $request->input("search_{$key}_field_{$fieldKey}");
            }
        }

        $upsertData = array_map(fn ($key, $value) => [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
            'description' => 'Search setting',
        ], array_keys($fields), array_values($fields));

        Setting::upsert($upsertData, ['key'], ['value', 'type', 'description']);

        return redirect()->route('backend.settings.search')->with('success', __('translation.saved'));
    }
}
