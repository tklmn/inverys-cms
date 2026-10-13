<?php

namespace Plugins\Multilingual\Http\Controllers;

use App\Contracts\ContentTranslator;
use App\Models\Plugin;
use App\Models\Setting;
use App\Services\PluginManager;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Plugins\Multilingual\DatabaseContentTranslator;

#[Middleware('permission:multilingual-settings')]
class SettingsController extends Controller
{
    public function edit(Locales $locales): View
    {
        // How many items are translated per language (a model counts once)
        // Portable (SQLite too): first distinct models per language, then count
        $distinct = DB::table(DatabaseContentTranslator::TABLE)
            ->select('locale', 'translatable_type', 'translatable_id')
            ->distinct();

        $counts = DB::query()->fromSub($distinct, 't')
            ->select('locale', DB::raw('COUNT(*) as total'))
            ->groupBy('locale')
            ->pluck('total', 'locale')
            ->all();

        $translator = app(ContentTranslator::class);

        return view('multilingual::admin.settings', [
            'languages' => $locales->all(),
            'default' => $locales->default(),
            'base' => $locales->base(),
            'counts' => $counts,
            'drafts' => $translator instanceof DatabaseContentTranslator ? $translator->draftCounts() : [],
            'packs' => $this->languagePacks(),
        ]);
    }

    /**
     * Installed language packs (plugins with "type": "language") with version and state.
     *
     * @return list<array{name: string, version: string, enabled: bool}>
     */
    private function languagePacks(): array
    {
        $enabled = Plugin::pluck('is_enabled', 'slug');

        return collect(app(PluginManager::class)->all())
            ->filter(fn ($manifest) => ($manifest['type'] ?? null) === 'language')
            ->map(fn ($manifest, $slug) => [
                'name' => __($manifest['name'] ?? $slug),
                'version' => (string) ($manifest['version'] ?? ''),
                'enabled' => (bool) ($enabled[$slug] ?? false),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function update(Request $request, Locales $locales): RedirectResponse
    {
        $data = $request->validate([
            'default_locale' => ['required', Rule::in($locales->codes())],
        ]);

        // The same setting as the interface's default language — an
        // installation has exactly one default language
        Setting::updateOrCreate(['key' => 'language'], ['value' => $data['default_locale']]);
        $locales->flush();

        return redirect()->route('backend.multilingual.settings')
            ->with('success', __('multilingual::ml.saved'));
    }

    /**
     * Prefill a language: copy all translations of one language into another
     * as a template. Fields already translated stay, slugs are not
     * copied. The original is not a source — empty fields show it
     * anyway, a copy would only hide later changes.
     */
    public function copy(Request $request, Locales $locales): RedirectResponse
    {
        $translations = array_values(array_diff($locales->codes(), [$locales->base()]));

        $data = $request->validate([
            'from' => ['required', Rule::in($translations)],
            'to' => ['required', Rule::in($translations), 'different:from'],
        ]);

        $translator = app(ContentTranslator::class);
        abort_unless($translator instanceof DatabaseContentTranslator, 404);

        $result = $translator->copyLocale($data['from'], $data['to']);
        $names = ['from' => $locales->native($data['from']), 'to' => $locales->native($data['to'])];

        return redirect()->route('backend.multilingual.settings')->with(
            $result['items'] > 0 ? 'success' : 'warning',
            $result['items'] > 0
                ? __('multilingual::ml.prefill_done', $names + ['items' => $result['items'], 'fields' => $result['fields']])
                : __('multilingual::ml.prefill_nothing', $names),
        );
    }
}
