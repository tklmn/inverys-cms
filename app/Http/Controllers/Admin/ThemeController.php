<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesPackageUpdates;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BlockValidator;
use App\Services\PackageInstaller;
use App\Services\ThemeManager;
use App\Services\Updates\PackageStore;
use App\Services\Updates\PackageUpdater;
use App\Services\Updates\UpdateChecker;
use App\Support\CodeAccess;
use App\Support\EditLocale;
use App\Support\ThemeTranslations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

#[Middleware('permission:manage-themes')]
class ThemeController extends Controller
{
    use HandlesPackageUpdates;

    public function index(ThemeManager $themeManager, UpdateChecker $updates, PackageStore $store): View
    {
        // Find themes that have been trashed (have soft-deleted settings)
        $trashedSlugs = Setting::onlyTrashed()
            ->where('key', 'like', 'theme.%')
            ->get()
            ->map(fn ($s) => explode('.', $s->key)[1] ?? null)
            ->filter()
            ->unique()
            ->toArray();

        $themes = collect($themeManager->all())
            ->reject(fn ($theme) => in_array($theme['slug'], $trashedSlugs))
            // Last backed-up state (before an update) — the way back
            ->map(fn ($theme) => $theme + ['backup' => $store->backups('theme', $theme['slug'])[0] ?? null])
            ->values()
            ->all();

        return view('backend.themes.index', [
            'themes' => $themes,
            'activeTheme' => $themeManager->getActive(),
            // Newer versions according to the last check (cms:check-updates)
            'updates' => $updates->availableFor('theme'),
            'checkedAt' => $updates->checkedAt(),
        ]);
    }

    public function activate(Request $request, ThemeManager $themeManager): RedirectResponse
    {
        $request->validate(['slug' => 'required|string']);

        if ($themeManager->activate($request->slug)) {
            return redirect()->route('backend.themes.index')
                ->with('success', __('Theme activated successfully.'));
        }

        return back()->withErrors(['slug' => __('Theme not found.')]);
    }

    public function customize(string $slug, ThemeManager $themeManager): View|RedirectResponse
    {
        $theme = $themeManager->get($slug);
        if (! $theme) {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('Theme not found.')]);
        }

        return view('backend.themes.customize', [
            'theme' => $theme,
            'settings' => $themeManager->getSettingsSchema($slug),
            'regions' => $themeManager->getRegionsSchema($slug),
            'savedSettings' => $themeManager->getSavedSettings($slug),
            'savedRegions' => $themeManager->getSavedRegions($slug),
            'customCss' => $themeManager->getCustomCss($slug),
        ]);
    }

    public function saveCustomization(Request $request, string $slug, ThemeManager $themeManager): RedirectResponse
    {
        $theme = $themeManager->get($slug);
        if (! $theme) {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('Theme not found.')]);
        }

        // Translation: only texts and regions (multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            $this->saveCustomizationTranslation($request, $slug, $locale, $themeManager);

            return redirect()->route('backend.themes.customize', ['slug' => $slug, 'lang' => $locale])
                ->with('success', __('Theme customization saved.'));
        }

        // Save settings
        $settingsSchema = $themeManager->getSettingsSchema($slug);
        foreach ($settingsSchema as $key => $schema) {
            $value = $request->input("settings.{$key}", $schema['default'] ?? '');

            // Boolean fields: checkbox not sent when unchecked
            if (($schema['type'] ?? '') === 'boolean') {
                $value = $request->boolean("settings.{$key}") ? '1' : '0';
            } elseif (($schema['type'] ?? '') === 'color') {
                // Colour values are injected into CSS and bypass cleanCss — enforce a safe format.
                $value = $this->sanitizeColor((string) $value, (string) ($schema['default'] ?? ''));
            }

            $themeManager->saveSetting($slug, $key, (string) $value);
        }

        // Save regions
        $regionsSchema = $themeManager->getRegionsSchema($slug);
        foreach ($regionsSchema as $key => $schema) {
            $blocksJson = (string) $request->input("regions.{$key}", '[]');
            // Valid JSON with valid blocks — otherwise every page of the website breaks
            $decoded = json_decode($blocksJson, true);
            if (is_array($decoded) && app(BlockValidator::class)->validate($decoded)) {
                $themeManager->saveRegion($slug, $key, $blocksJson);
            }
        }

        // Save custom CSS
        $themeManager->saveCustomCss($slug, $request->input('custom_css') ?? '');

        return redirect()->route('backend.themes.customize', $slug)
            ->with('success', __('Theme customization saved.'));
    }

    /**
     * Validate a colour value that will be injected into CSS. Allows hex, rgb(a),
     * hsl(a) and plain named colours; anything else falls back to the default so a
     * value like "red; } body { ... }" cannot break out into the stylesheet.
     */
    private function sanitizeColor(string $value, string $default = ''): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        $patterns = [
            '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i',
            '/^rgba?\(\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*){2}(,\s*[\d.]+\s*)?\)$/i',
            '/^hsla?\(\s*[\d.]+\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*(,\s*[\d.]+\s*)?\)$/i',
            '/^[a-z]+$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return $value;
            }
        }

        return $default;
    }

    public function saveRegion(Request $request, string $slug, string $region, ThemeManager $themeManager): JsonResponse
    {
        $theme = $themeManager->get($slug);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $blocksJson = (string) $request->input('blocks', '[]');
        $decoded = json_decode($blocksJson, true);
        if (is_array($decoded) && app(BlockValidator::class)->validate($decoded)) {
            // In translation mode, save the language's blocks
            if ($locale = EditLocale::current($request)) {
                $this->saveRegionTranslation($slug, $region, $locale, $decoded, $themeManager);

                return response()->json(['ok' => true]);
            }

            $themeManager->saveRegion($slug, $region, $blocksJson);

            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => 'Invalid JSON'], 422);
    }

    /**
     * Save translated theme texts. Only text/textarea — colours, images
     * and toggles apply to all languages and stay untouched.
     */
    protected function saveCustomizationTranslation(Request $request, string $slug, string $locale, ThemeManager $themeManager): void
    {
        $saved = $themeManager->getSavedSettings($slug);

        foreach ($themeManager->getSettingsSchema($slug) as $key => $schema) {
            if (! ThemeTranslations::isText($slug, $key)) {
                continue;
            }

            ThemeTranslations::save(
                "theme.{$slug}.{$key}",
                $locale,
                $request->input("settings.{$key}"),
                (string) ($saved[$key] ?? $schema['default'] ?? ''),
                'theme_setting',
            );
        }

        foreach ($themeManager->getRegionsSchema($slug) as $key => $schema) {
            $decoded = json_decode((string) $request->input("regions.{$key}", '[]'), true);
            if (is_array($decoded) && app(BlockValidator::class)->validate($decoded)) {
                $this->saveRegionTranslation($slug, $key, $locale, $decoded, $themeManager);
            }
        }

        $themeManager->clearCache($slug);
    }

    protected function saveRegionTranslation(string $slug, string $key, string $locale, array $blocks, ThemeManager $themeManager): void
    {
        $original = $themeManager->getSavedRegions($slug)[$key] ?? ($themeManager->getRegionsSchema($slug)[$key]['default'] ?? []);

        ThemeTranslations::save(
            "theme.{$slug}.region.{$key}",
            $locale,
            $blocks ? json_encode($blocks) : null,
            is_string($original) ? $original : json_encode($original),
            'theme_region',
        );
    }

    public function upload(Request $request, PackageInstaller $installer): RedirectResponse
    {
        // A theme package is code (Blade/PHP) — admins only, only when enabled
        abort_unless(CodeAccess::allowed($request->user()), 403);

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $result = $installer->installTheme($request->file('package'));

        if (! $result['success']) {
            return back()->withErrors(['package' => $result['error']]);
        }

        // Remove soft-deleted theme settings so the theme shows up again — only
        // the trashed ones: an update keeps the customization of the theme
        Setting::onlyTrashed()->where('key', 'like', "theme.{$result['slug']}.%")->forceDelete();

        return redirect()->route('backend.themes.index')
            ->with('success', ! empty($result['updated'])
                ? __('translation.updates.uploaded_update', ['slug' => $result['slug'], 'version' => $result['version']])
                : __('Theme ":slug" uploaded successfully. You can now activate it.', ['slug' => $result['slug']]));
    }

    /** To the latest version of the source ("repository" in theme.json) */
    public function update(Request $request, PackageUpdater $updater, string $slug): RedirectResponse
    {
        return $this->updatePackage($request, $updater, 'theme', $slug, 'backend.themes.index');
    }

    /** Restore the state before the last update */
    public function restore(Request $request, PackageUpdater $updater, string $slug): RedirectResponse
    {
        return $this->restorePackage($request, $updater, 'theme', $slug, 'backend.themes.index');
    }

    public function destroy(string $slug, ThemeManager $themeManager): RedirectResponse
    {
        abort_unless(CodeAccess::allowed(auth()->user()), 403);

        // The default theme is protected and cannot be removed
        if ($slug === 'default') {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('translation.theme_protected')]);
        }

        // Only known themes with a valid slug — the slug becomes a directory
        // path below; "%2e%2e" would otherwise mean deleteDirectory(themes/..)
        if (! preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?$/', $slug) || ! $themeManager->get($slug)) {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('Theme not found.')]);
        }

        // If this theme is currently active, switch to default first
        if ($themeManager->getActive() === $slug) {
            $themeManager->activate('default');
        }

        // Remove all theme settings permanently
        Setting::withTrashed()->where('key', 'like', "theme.{$slug}.%")->forceDelete();

        // Remove the theme directory (uploaded themes only, not vendor)
        $themeDir = config('cms.themes_path').'/'.$slug;
        if (is_dir($themeDir)) {
            File::deleteDirectory($themeDir);
        }

        return redirect()->route('backend.themes.index')
            ->with('success', __('translation.theme_deleted'));
    }
}
