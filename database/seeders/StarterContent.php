<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\ContentTranslator;
use App\Models\Page;
use App\Models\Setting;
use App\Services\ThemeManager;
use App\Support\Locales;
use App\Support\SettingTranslations;
use Database\Seeders\Demo\Blocks as B;

/**
 * Starter content for an installation without demo content.
 *
 * The installer always creates a home page; without sample pages it also
 * replaces the themes' footer area, whose default links to demo pages
 * (/about, /contact) and a sample address. Styling is done only through
 * the page builder's block options, and only what always exists is linked
 * (home page, blog, backend).
 *
 * All texts come from lang/<locale>/install.php and are created in the
 * application's current language. translate() layers them as a translation
 * for every other installed language — with the same block IDs.
 */
final class StarterContent
{
    /** Prefix of the home page blocks (IDs start-1, start-2 …) */
    public const HOME_PREFIX = 'start';

    private const FOOTER_PREFIX = 'start-footer';

    /** Region that receives the installer footer band */
    private const FOOTER_REGION = 'footer_content';

    public static function homeTitle(): string
    {
        return __('install.home_title');
    }

    /** @return list<array<string, mixed>> */
    public static function homeBlocks(string $siteName, string $blogUrl): array
    {
        B::page(self::HOME_PREFIX);

        return [
            B::hero([
                'eyebrow' => __('install.home_eyebrow'),
                'title' => __('install.home_heading', ['name' => $siteName]),
                'text' => __('install.home_text'),
                'primary_text' => __('install.home_cta'),
                'primary_url' => '/backend',
                'secondary_text' => __('install.home_cta_blog'),
                'secondary_url' => $blogUrl,
            ], ['size' => 'large']),

            B::band('soft', [
                B::intro(__('install.home_welcome_eyebrow'), __('install.home_welcome_title'), e(__('install.home_welcome_text'))),
                B::cards([
                    ['icon' => 'layout-dashboard', 'title' => __('install.home_step_pages_title'), 'content' => __('install.home_step_pages_text')],
                    ['icon' => 'palette', 'title' => __('install.home_step_design_title'), 'content' => __('install.home_step_design_text')],
                    ['icon' => 'pen-tool', 'title' => __('install.home_step_blog_title'), 'content' => __('install.home_step_blog_text')],
                ], ['style' => 'tinted', 'align' => 'center', 'icon_style' => 'gradient']),
            ], 'py-20'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function footerBlocks(string $siteName, string $blogUrl): array
    {
        B::page(self::FOOTER_PREFIX);

        return [
            B::columns([
                [
                    B::heading($siteName, 'h3'),
                    B::text('<p>'.e(__('install.footer_about')).'</p>'),
                ],
                [
                    B::heading(__('install.footer_links'), 'h3'),
                    B::links([
                        ['text' => self::homeTitle(), 'url' => '/', 'target' => '_self'],
                        ['text' => __('install.footer_blog'), 'url' => $blogUrl, 'target' => '_self'],
                    ]),
                ],
            ], 'gap-8'),
        ];
    }

    public static function copyright(string $siteName): string
    {
        return __('install.footer_copyright', ['year' => date('Y'), 'name' => $siteName]);
    }

    /**
     * Set theme texts: the copyright line with the site name instead of
     * "© 2026 Inverys CMS" and — without sample pages — a footer area without
     * links to pages that do not exist.
     */
    public static function applyThemeTexts(string $siteName, string $blogUrl, bool $replaceFooter): void
    {
        $themes = app(ThemeManager::class);

        foreach (array_keys($themes->all()) as $slug) {
            if (isset($themes->getSettingsSchema($slug)['footer_text'])) {
                $themes->saveSetting($slug, 'footer_text', self::copyright($siteName));
            }

            if ($replaceFooter && isset($themes->getRegionsSchema($slug)[self::FOOTER_REGION])) {
                $themes->saveRegion($slug, self::FOOTER_REGION, json_encode(self::footerBlocks($siteName, $blogUrl)));
            }
        }
    }

    /**
     * Translate the starter content for every other installed language
     * (only with the multilingual plugin). Existing translations stay.
     */
    public static function translate(string $siteName, string $blogUrl, bool $withFooter): void
    {
        $locales = app(Locales::class);
        $translator = app(ContentTranslator::class);

        if (! $translator->enabled()) {
            return;
        }

        $base = $locales->base();
        $home = Page::where('slug', '/')->first();
        $isStarterHome = $home && str_starts_with((string) ($home->getOriginalValue('blocks')[0]['id'] ?? ''), self::HOME_PREFIX.'-');
        $themes = app(ThemeManager::class);

        foreach ($locales->codes() as $locale) {
            if ($locale === $base) {
                continue;
            }

            self::inLocale($locale, function () use ($locale, $translator, $home, $isStarterHome, $siteName, $blogUrl, $withFooter, $themes) {
                if ($isStarterHome && array_filter($translator->all($home, $locale), fn ($v) => $v !== null && $v !== '') === []) {
                    $translator->save($home, $locale, ['title' => self::homeTitle(), 'blocks' => self::homeBlocks($siteName, $blogUrl)]);
                }

                foreach (array_keys($themes->all()) as $slug) {
                    $key = "theme.{$slug}.footer_text";
                    if (isset($themes->getSettingsSchema($slug)['footer_text']) && SettingTranslations::translated($key, $locale) === null) {
                        SettingTranslations::save($key, $locale, self::copyright($siteName), (string) Setting::where('key', $key)->value('value'), 'theme_setting');
                    }

                    $key = "theme.{$slug}.region.".self::FOOTER_REGION;
                    if ($withFooter && isset($themes->getRegionsSchema($slug)[self::FOOTER_REGION]) && SettingTranslations::translated($key, $locale) === null) {
                        SettingTranslations::save($key, $locale, json_encode(self::footerBlocks($siteName, $blogUrl)), (string) Setting::where('key', $key)->value('value'), 'theme_region');
                    }

                    $themes->clearCache($slug);
                }
            });
        }
    }

    /** Temporarily generate texts in another language */
    private static function inLocale(string $locale, callable $callback): void
    {
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
