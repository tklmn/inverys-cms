<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Models\Setting;
use App\Services\Cms\EloquentSettingRepository;
use App\Sitemap\SitemapGenerator;
use App\Support\Redirects;
use App\Support\Seo\DocumentTitle;
use App\Support\SeoSettings;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → SEO: indexing (allow/block search engines),
 * sitemap (on/off, content per provider, cache duration), robots.txt
 * (automatic, custom content or off; sitemap line) and what every page
 * tells search engines and social networks (default description, title
 * format, default social image, organisation, verification codes).
 */
#[Middleware('auth')]
#[Middleware('permission:manage-seo-settings', only: ['edit', 'save'])]
class SeoSettingController extends Controller
{
    public function edit(SitemapGenerator $sitemap): View
    {
        $providers = [];
        foreach ($sitemap->toggleableProviders() as $key => $label) {
            $providers[$key] = [
                'label' => __($label),
                'hint' => Lang::has($label.'_hint') ? __($label.'_hint') : null,
                'enabled' => SeoSettings::sitemapIncludes($key),
            ];
        }

        $cacheOptions = [];
        foreach (SeoSettings::CACHE_MINUTES as $minutes) {
            $cacheOptions[$minutes] = match (true) {
                $minutes === 0 => __('translation.seo.cache_none'),
                $minutes < 60 => __('translation.seo.cache_minutes', ['count' => $minutes]),
                default => trans_choice('translation.seo.cache_hours', intdiv($minutes, 60), ['count' => intdiv($minutes, 60)]),
            };
        }

        return view('backend.settings.seo', [
            'settings' => [
                'sitemap_enabled' => SeoSettings::sitemapEnabled(),
                'sitemap_cache_ttl' => SeoSettings::sitemapCacheMinutes(),
                'robots_allow_indexing' => SeoSettings::allowIndexing(),
                'robots_mode' => SeoSettings::robotsMode(),
                'robots_sitemap_line' => SeoSettings::robotsSitemapLine(),
                'robots_extra' => SeoSettings::robotsExtra(),
                'robots_custom' => SeoSettings::robotsCustom(),
                // Original values — translations of the description are maintained under Languages
                'default_description' => (string) Setting::where('key', 'seo.default_description')->value('value'),
                'title_order' => DocumentTitle::order(),
                'title_separator' => DocumentTitle::separator(),
                'default_og_image' => (string) setting('seo.default_og_image', ''),
                'organization_logo' => (string) setting('seo.organization_logo', ''),
                'social_profiles' => (string) setting('seo.social_profiles', ''),
                'google_verification' => (string) setting('seo.google_verification', ''),
                'bing_verification' => (string) setting('seo.bing_verification', ''),
            ],
            'titleOrders' => DocumentTitle::ORDERS,
            'titleSeparators' => DocumentTitle::SEPARATORS,
            'titleExample' => (string) setting('site_name', __('translation.app_name')),
            'themeLogo' => (string) theme('logo_url', ''),
            'redirectCount' => Redirects::enabled() ? Redirect::count() : 0,
            'robotsModes' => [
                SeoSettings::ROBOTS_AUTO => ['icon' => 'wand-sparkles', 'tone' => 'accent'],
                SeoSettings::ROBOTS_CUSTOM => ['icon' => 'pencil', 'tone' => 'violet'],
                SeoSettings::ROBOTS_OFF => ['icon' => 'eye-off', 'tone' => 'caution'],
            ],
            'sitemapUrl' => route('sitemap'),
            'searchConsoleUrl' => SeoSettings::GOOGLE_SEARCH_CONSOLE_URL,
            'bingWebmasterUrl' => SeoSettings::BING_WEBMASTER_URL,
            'providers' => $providers,
            'cacheOptions' => $cacheOptions,
            'isIndex' => $sitemap->isIndex(),
            'robotsPreview' => SeoSettings::robotsTxt(),
            'staticRobotsFile' => is_file(public_path('robots.txt')),
        ]);
    }

    public function save(Request $request, SitemapGenerator $sitemap): RedirectResponse
    {
        $providerKeys = array_keys($sitemap->toggleableProviders());

        $rules = [
            'sitemap_enabled' => 'required|in:on,off',
            'sitemap_cache_ttl' => ['required', 'integer', Rule::in(SeoSettings::CACHE_MINUTES)],
            'robots_allow_indexing' => 'required|in:on,off',
            'robots_mode' => ['required', Rule::in(SeoSettings::ROBOTS_MODES)],
            'robots_sitemap_line' => 'required|in:on,off',
            'robots_extra' => ['nullable', 'string', 'max:'.SeoSettings::ROBOTS_EXTRA_MAX, $this->robotsRule(false)],
            // Custom content: the same lines, plus custom sitemap lines
            'robots_custom' => ['nullable', 'string', 'max:'.SeoSettings::ROBOTS_CUSTOM_MAX, $this->robotsRule(true)],
            'default_description' => ['nullable', 'string', 'max:'.SeoSettings::DEFAULT_DESCRIPTION_MAX],
            'title_order' => ['nullable', Rule::in(DocumentTitle::ORDERS)],
            'title_separator' => ['nullable', Rule::in(DocumentTitle::SEPARATORS)],
            // Path from the media library or full address
            'default_og_image' => ['nullable', 'string', 'max:255', 'regex:#^(https?://[^\s<>"]+|/?[^\s<>":]+)$#i'],
            'organization_logo' => ['nullable', 'string', 'max:255', 'regex:#^(https?://[^\s<>"]+|/?[^\s<>":]+)$#i'],
            'social_profiles' => ['nullable', 'string', 'max:5000', $this->profilesRule()],
            'google_verification' => ['nullable', 'string', 'max:500', $this->verificationRule()],
            'bing_verification' => ['nullable', 'string', 'max:500', $this->verificationRule()],
        ];
        foreach ($providerKeys as $key) {
            $rules['sitemap_include_'.$key] = 'nullable|in:on,off';
        }

        $request->validate($rules, [], [
            'sitemap_cache_ttl' => __('translation.seo.sitemap_cache'),
            'robots_extra' => __('translation.seo.robots_extra'),
            'robots_custom' => __('translation.seo.robots_custom'),
            'default_description' => __('translation.seo.default_description'),
            'default_og_image' => __('translation.seo.default_og_image'),
            'organization_logo' => __('translation.seo.organization_logo'),
            'social_profiles' => __('translation.seo.social_profiles'),
            'google_verification' => __('translation.seo.google_verification'),
            'bing_verification' => __('translation.seo.bing_verification'),
        ]);

        $fields = [
            'seo.sitemap_enabled' => $request->input('sitemap_enabled'),
            'seo.sitemap_cache_ttl' => (string) (int) $request->input('sitemap_cache_ttl'),
            'seo.robots_allow_indexing' => $request->input('robots_allow_indexing'),
            'seo.robots_mode' => $request->input('robots_mode'),
            'seo.robots_sitemap_line' => $request->input('robots_sitemap_line'),
            'seo.robots_extra' => self::normalize($request->input('robots_extra')),
            'seo.robots_custom' => self::normalize($request->input('robots_custom')),
            'seo.default_description' => trim((string) preg_replace('/\s+/u', ' ', self::normalize($request->input('default_description')))),
            'seo.title_order' => $request->input('title_order') ?: DocumentTitle::order(),
            'seo.title_separator' => $request->input('title_separator') ?: DocumentTitle::separator(),
            'seo.default_og_image' => self::normalize($request->input('default_og_image')),
            'seo.organization_logo' => self::normalize($request->input('organization_logo')),
            'seo.social_profiles' => implode("\n", SeoSettings::parseProfiles(self::normalize($request->input('social_profiles')))),
            'seo.google_verification' => SeoSettings::verificationCode(self::normalize($request->input('google_verification'))),
            'seo.bing_verification' => SeoSettings::verificationCode(self::normalize($request->input('bing_verification'))),
        ];
        foreach ($providerKeys as $key) {
            $fields['seo.sitemap_include_'.$key] = $request->input('sitemap_include_'.$key, 'on');
        }

        Setting::upsert(array_map(fn ($key, $value) => [
            'key' => $key,
            'value' => $value,
            'type' => 'string',
            'description' => 'SEO setting',
        ], array_keys($fields), array_values($fields)), ['key'], ['value', 'type', 'description']);

        // upsert() fires no model events — clear the settings cache ourselves.
        // Content or cache duration may have changed: rebuild the sitemap.
        EloquentSettingRepository::clearCache();
        SitemapGenerator::forget();

        return redirect()->route('backend.settings.seo')->with('success', __('translation.saved'));
    }

    /** Line check for robots.txt rules; the message names the first invalid line */
    private function robotsRule(bool $allowSitemap): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowSitemap) {
            $line = is_string($value) ? SeoSettings::invalidRobotsLine($value, $allowSitemap) : null;

            if ($line !== null) {
                $fail(__($allowSitemap ? 'translation.seo.robots_custom_invalid' : 'translation.seo.robots_extra_invalid', [
                    'line' => mb_strimwidth($line, 0, 80, '…'),
                ]));
            }
        };
    }

    /** One http(s) address per line; the message names the first invalid one */
    private function profilesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            foreach (preg_split('/\r\n|\r|\n/', is_string($value) ? $value : '') ?: [] as $line) {
                $line = trim($line);
                if ($line !== '' && (! filter_var($line, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $line))) {
                    $fail(__('translation.seo.social_profiles_invalid', ['line' => mb_strimwidth($line, 0, 80, '…')]));

                    return;
                }
            }
        };
    }

    /** The code alone or the whole meta tag of the webmaster tool */
    private function verificationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (is_string($value) && trim($value) !== '' && SeoSettings::verificationCode($value) === '') {
                $fail(__('translation.seo.verification_invalid'));
            }
        };
    }

    private static function normalize(mixed $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", is_string($text) ? $text : ''));
    }
}
