<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Inline renderer for the bundled Lucide icons.
 *
 * The SVGs live as files under resources/icons/lucide/ and are embedded at
 * runtime so they inherit the text color and no additional request
 * is needed. Results are cached per request.
 *
 * On normal page views (ServeIconSprite) each icon appears only once
 * as a <symbol> in the page; the occurrences reference it.
 */
final class Icon
{
    /**
     * Mapping of Bootstrap Icons names to Lucide.
     *
     * The table lives in resources/icons/aliases.json because the JavaScript
     * (resources/js/backend/ui/icons.js) needs the same one — icons created by
     * script should not be named differently from those in Blade.
     */
    private const ALIASES_FILE = 'icons/aliases.json';

    /** @var array<string, string>|null */
    private static ?array $aliases = null;

    /** @var array<string, string|null> */
    private static array $cache = [];

    /** References to a sprite instead of full SVGs (set by ServeIconSprite) */
    private static bool $sprite = false;

    /** Fill, stroke and scale — on the sprite symbol or on the embedded SVG */
    private const PRESENTATION = 'fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"';

    public static function useSprite(bool $sprite): void
    {
        self::$sprite = $sprite;
    }

    public static function svg(string $name, string $class = '', float $strokeWidth = 1.75): ?string
    {
        $markup = self::load($name);

        if ($markup === null) {
            return null;
        }

        // ui-icon sets display:inline-block — without it Tailwind's preflight
        // turns every SVG into a block, and an icon in running text slips onto
        // its own line above the label.
        $attributes = sprintf(
            'width="1em" height="1em" stroke-width="%s" aria-hidden="true" focusable="false" class="%s"',
            $strokeWidth,
            e(trim('ui-icon '.$class)),
        );

        if (self::$sprite) {
            return '<svg '.$attributes.'><use href="#ui-i-'.self::resolve($name).'"></use></svg>';
        }

        return (string) preg_replace('/^<svg\b[^>]*/', '<svg viewBox="0 0 24 24" '.self::PRESENTATION.' '.$attributes, $markup, 1);
    }

    /** The icon as a <symbol> for a page's sprite */
    public static function symbol(string $name): ?string
    {
        $inner = self::inner($name);

        return $inner === null ? null : '<symbol id="ui-i-'.self::resolve($name).'" viewBox="0 0 24 24" '.self::PRESENTATION.'>'.$inner.'</symbol>';
    }

    /** Turn a reference back into the full SVG (for responses without a sprite) */
    public static function inlineFromSprite(string $name, string $attributes): ?string
    {
        $inner = self::inner($name);

        return $inner === null ? null : '<svg viewBox="0 0 24 24" '.self::PRESENTATION.' '.$attributes.'>'.$inner.'</svg>';
    }

    private static function inner(string $name): ?string
    {
        $markup = self::load($name);

        return $markup === null ? null : (string) preg_replace(['/^<svg\b[^>]*>/', '/<\/svg>\s*$/'], '', $markup);
    }

    /** Name after applying the aliases (Bootstrap Icons names) */
    private static function resolve(string $name): string
    {
        return self::aliases()[$name] ?? $name;
    }

    public static function exists(string $name): bool
    {
        return self::load($name) !== null;
    }

    /** @return array<string, string> */
    private static function aliases(): array
    {
        if (self::$aliases === null) {
            $decoded = json_decode((string) @file_get_contents(resource_path(self::ALIASES_FILE)), true);
            self::$aliases = is_array($decoded) ? $decoded : [];
        }

        return self::$aliases;
    }

    private static function load(string $name): ?string
    {
        // Allow only simple icon names — the name may come from plugin config.
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return null;
        }

        $name = self::aliases()[$name] ?? $name;

        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        $path = resource_path('icons/lucide/'.$name.'.svg');

        if (! is_file($path)) {
            return self::$cache[$name] = null;
        }

        $markup = trim((string) file_get_contents($path));

        // Remove the license comment from lucide-static
        $markup = Str::of($markup)->replaceMatches('/^<!--.*?-->\s*/s', '')->toString();

        return self::$cache[$name] = $markup;
    }
}
