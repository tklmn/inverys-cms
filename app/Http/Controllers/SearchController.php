<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! auth()->check() && setting('search.guest_access', 'on') === 'off') {
            return redirect()->route('login');
        }

        // ?q[]=… would otherwise yield an array → TypeError/500
        $query = is_string($request->input('q')) ? $request->input('q') : '';
        $minLength = (int) setting('search.min_length', 2);
        $perPage = (int) setting('search.per_page', 10);
        $results = collect();

        if (mb_strlen($query) >= $minLength) {
            $providers = app()->bound('plugin.search_providers')
                ? app()->make('plugin.search_providers')
                : [];

            foreach ($providers as $provider) {
                if (setting("search.include_{$provider->key()}", 'on') !== 'on') {
                    continue;
                }

                // Build per-provider field flags from settings
                $fieldFlags = [];
                foreach ($provider->fields() as $fieldKey => $fieldConfig) {
                    $settingKey = "search.{$provider->key()}.fields.{$fieldKey}";
                    $fieldFlags[$fieldKey] = setting($settingKey, $fieldConfig['default'] ?? 'on') === 'on';
                }

                $results = $results->merge($provider->search($query, $fieldFlags));
            }

            $results = $results->sortBy('title')->values();

            $page = (int) $request->input('page', 1);
            $total = $results->count();
            $results = new LengthAwarePaginator(
                $results->forPage($page, $perPage),
                $total,
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->only('q')]
            );
        }

        $view = view()->exists('theme::search') ? 'theme::search' : 'frontend.search';

        return view($view, [
            'query' => $query,
            'results' => $results,
            'minLength' => $minLength,
        ]);
    }
}
