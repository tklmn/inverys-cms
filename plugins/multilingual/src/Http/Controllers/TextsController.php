<?php

namespace Plugins\Multilingual\Http\Controllers;

use App\Contracts\SettingRepository;
use App\Support\EditLocale;
use App\Support\Locales;
use App\Support\SettingTranslations;
use App\Support\TranslatableSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Translate settings whose text visitors see: site name,
 * cookie banner, announcement bar … — all in one place instead of a
 * language selector on every settings page.
 *
 * Core and plugins register which settings these are in the
 * TranslatableSettings registry. The original is still maintained on
 * the respective settings page.
 */
#[Middleware('permission:multilingual-settings')]
class TextsController extends Controller
{
    public function edit(Request $request, Locales $locales, TranslatableSettings $registry, SettingRepository $settings): View|RedirectResponse
    {
        $locale = EditLocale::current($request);

        // The original has nothing to edit here — show the first translation straight away
        if ($locale === null) {
            $first = collect($locales->codes())->first(fn ($code) => $code !== $locales->base());

            return $first
                ? redirect()->route('backend.multilingual.texts', ['lang' => $first])
                : redirect()->route('backend.multilingual.settings');
        }

        $groups = [];
        $groupKeys = [];
        foreach ($registry->all() as $key => $definition) {
            // Only settings that exist — a disabled plugin has none
            if (! $settings->has($key)) {
                continue;
            }

            $original = $this->original($key);
            $translated = SettingTranslations::translated($key, $locale);

            if ($definition['type'] === 'list') {
                $items = json_decode((string) $original, true);
                $texts = json_decode((string) $translated, true);
                $definition['items'] = is_array($items) ? array_values($items) : [];
                $definition['texts'] = is_array($texts) ? $texts : [];
            } else {
                $definition['original'] = (string) $original;
                $definition['translated'] = (string) $translated;
            }

            $groups[__($definition['group'])][$key] = $definition;
            // Untranslated group key: the view derives the icon and tone from it
            $groupKeys[__($definition['group'])] = $definition['group'];
        }

        return view('multilingual::admin.texts', [
            'groups' => $groups,
            'groupKeys' => $groupKeys,
            'locale' => $locale,
            'language' => $locales->native($locale),
            'base' => $locales->native($locales->base()),
        ]);
    }

    public function update(Request $request, TranslatableSettings $registry, SettingRepository $settings): RedirectResponse
    {
        $locale = EditLocale::current($request);
        abort_if($locale === null, 404);

        $input = $request->validate([
            'texts' => 'array',
            'texts.*' => 'nullable',
            'lists' => 'array',
        ]);

        foreach ($registry->all() as $key => $definition) {
            if (! $settings->has($key)) {
                continue;
            }

            $field = str_replace('.', '__', $key);
            $original = (string) $this->original($key);

            if ($definition['type'] === 'list') {
                // Only save filled-in texts; empty means "show the original"
                $texts = [];
                foreach ((array) ($input['lists'][$field] ?? []) as $id => $values) {
                    $values = array_filter(array_intersect_key((array) $values, $definition['fields']), fn ($v) => filled($v));
                    if ($values) {
                        $texts[(string) $id] = array_map(fn ($v) => mb_substr((string) $v, 0, 1000), $values);
                    }
                }

                SettingTranslations::save($key, $locale, $texts ? json_encode($texts, JSON_UNESCAPED_UNICODE) : null, $original);

                continue;
            }

            $value = $input['texts'][$field] ?? null;
            SettingTranslations::save($key, $locale, $value === null ? null : mb_substr((string) $value, 0, 5000), $original);
        }

        return redirect()->route('backend.multilingual.texts', ['lang' => $locale])
            ->with('success', __('multilingual::ml.texts_saved'));
    }

    /** Original value without translation (the store only translates in the frontend) */
    private function original(string $key): mixed
    {
        return app(SettingRepository::class)->get($key);
    }
}
