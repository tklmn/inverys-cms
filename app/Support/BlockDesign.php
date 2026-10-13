<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\HtmlHelper;
use Illuminate\Support\HtmlString;

/**
 * Design options of the page builder blocks → CSS classes of the website.
 *
 * Editors pick "gradient" or "centered" instead of having to know Tailwind
 * classes. Unknown values produce no class; custom classes still go through
 * the "Background" and "CSS class" fields (for advanced users).
 * The classes live in resources/css/frontend/components/site-design.css.
 */
final class BlockDesign
{
    /** Surfaces for section, container and hero */
    public const SURFACES = ['soft', 'tint', 'gradient', 'dark', 'glow'];

    /** Surfaces on which the text turns light */
    private const INVERTED = ['gradient', 'dark'];

    public static function surface(?string $style): string
    {
        if (! in_array($style, self::SURFACES, true)) {
            return '';
        }

        return 'site-surface-'.$style.(in_array($style, self::INVERTED, true) ? ' site-inverted' : '');
    }

    /** Overlay on a background image → CSS class; dark and gradient switch the text to light */
    private const OVERLAYS = ['dark' => 'site-bg-dark site-inverted', 'brand' => 'site-bg-brand site-inverted', 'light' => 'site-bg-light', 'none' => ''];

    /**
     * Background image of a block. It is decorative (alt="") unless the
     * editor switched that off and entered a text (data.bg_alt).
     *
     * @return array{class: string, src: string|null, alt: string}
     */
    public static function background(array $settings, array $data = []): array
    {
        $image = trim((string) ($settings['bg_image'] ?? ''));

        if ($image === '') {
            return ['class' => '', 'src' => null, 'alt' => ''];
        }

        $overlay = self::OVERLAYS[$settings['bg_overlay'] ?? 'dark'] ?? self::OVERLAYS['dark'];
        $src = preg_match('#^https?://#', $image) ? HtmlHelper::safeUrl($image) : asset(ltrim($image, '/'));
        $alt = self::alt($data['bg_alt'] ?? '', ($settings['bg_decorative'] ?? true) !== false);

        return ['class' => trim('site-has-bg '.$overlay), 'src' => $src, 'alt' => $alt];
    }

    /** Container option "spacing between blocks" */
    public static function gap(?string $gap): string
    {
        return match ($gap) {
            'none' => 'site-gap-none',
            'sm' => 'site-gap-sm',
            'lg' => 'site-gap-lg',
            default => 'site-gap-md',
        };
    }

    /** Visibility per device */
    public static function visibility(?string $visibility): string
    {
        return match ($visibility) {
            'desktop' => 'site-only-desktop',
            'mobile' => 'site-only-mobile',
            default => '',
        };
    }

    /**
     * Anchor for jump links (#contact): only lowercase letters, digits, - and _.
     * The editor may include a leading #.
     */
    public static function anchor(?string $anchor): string
    {
        return trim(preg_replace('/[^a-z0-9_-]+/', '-', strtolower(ltrim(trim((string) $anchor), '#'))), '-');
    }

    public static function align(?string $align): string
    {
        return match ($align) {
            'center' => 'site-text-center',
            'right' => 'site-text-right',
            default => '',
        };
    }

    /**
     * CSS length for style="height: …" (spacer, slider): only number + unit.
     * Otherwise an editor could append further declarations, such as
     * "1px;position:fixed;inset:0" — an overlay covering the whole page.
     */
    public static function length(mixed $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d+(\.\d+)?(px|rem|em|vh|vw|%)$/', $value) ? $value : $default;
    }

    /* ── Accessibility ─────────────────────────────────────────────── */

    /** Elements a container or section may be rendered as (landmarks) */
    public const ELEMENTS = ['div', 'section', 'aside', 'nav'];

    /** Heading levels below the page title */
    public const HEADING_LEVELS = ['h2', 'h3', 'h4', 'h5', 'h6'];

    /** Roles of the alert block: "none" outputs no role at all */
    public const ALERT_ROLES = ['none', 'note', 'status', 'alert'];

    /** Tags that never carry block attributes (lang …) */
    private const NON_CONTENT_TAGS = ['script', 'style', 'link', 'meta', 'template'];

    /**
     * Language of a block as a BCP 47 tag ("de", "en-GB", "sr-Latn-RS").
     * Underscores become hyphens and the case is normalised; anything that is
     * not a well-formed tag gives '' — the block then inherits the page language.
     */
    public static function lang(mixed $value): string
    {
        $value = is_string($value) ? str_replace('_', '-', trim($value)) : '';

        if ($value === '' || strlen($value) > 35 || ! preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{1,8})*$/i', $value)) {
            return '';
        }

        $parts = explode('-', $value);
        foreach ($parts as $i => $part) {
            $parts[$i] = match (true) {
                $i === 0 => strtolower($part),
                strlen($part) === 4 && ctype_alpha($part) => ucfirst(strtolower($part)),
                strlen($part) === 2 && ctype_alpha($part) => strtoupper($part),
                default => strtolower($part),
            };
        }

        return implode('-', $parts);
    }

    /** Element of a container/section: div, section, aside or nav — otherwise the default */
    public static function element(mixed $value, string $default): string
    {
        return in_array($value, self::ELEMENTS, true) ? $value : $default;
    }

    /**
     * Heading level h2–h6; anything else gives the default. An empty default
     * means "no heading" (e.g. alert title as a paragraph).
     */
    public static function headingLevel(mixed $value, string $default): string
    {
        return in_array($value, self::HEADING_LEVELS, true) ? $value : $default;
    }

    /**
     * Alternative text of an image: empty for decorative images, otherwise the
     * entered text — or the fallback (e.g. the card title of older content).
     */
    public static function alt(mixed $alt, mixed $decorative = false, string $fallback = ''): string
    {
        if (filter_var($decorative, FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        $alt = trim(is_string($alt) ? $alt : '');

        return $alt !== '' ? $alt : trim($fallback);
    }

    /** Role of the alert block; empty = the non-assertive default "status", "none" = no role */
    public static function alertRole(mixed $value): ?string
    {
        $role = in_array($value, self::ALERT_ROLES, true) ? $value : 'status';

        return $role === 'none' ? null : $role;
    }

    /**
     * Does a link open in a new tab? Explicit values win; content without a
     * value keeps its previous behaviour ($legacy, e.g. external links in the
     * buttons block, every logo link).
     */
    public static function newTab(mixed $target, bool $legacy = false): bool
    {
        return match ($target) {
            '_blank' => true,
            '_self' => false,
            default => $legacy,
        };
    }

    /**
     * Attributes of a link: target/rel for a new tab and an optional
     * accessible name. The name then also announces the new tab, since an
     * aria-label replaces the visually hidden hint inside the link.
     */
    public static function linkAttributes(bool $newTab, mixed $label = null): HtmlString
    {
        $html = $newTab ? ' target="_blank" rel="noopener noreferrer"' : '';
        $label = trim(is_string($label) ? $label : '');

        if ($label !== '') {
            $html .= ' aria-label="'.e($newTab ? $label.' '.__('translation.frontend.opens_new_tab') : $label).'"';
        }

        return new HtmlString($html);
    }

    /** Visually hidden "(opens in a new tab)" inside a link */
    public static function newTabHint(bool $newTab): HtmlString
    {
        return new HtmlString($newTab ? '<span class="sr-only"> '.e(__('translation.frontend.opens_new_tab')).'</span>' : '');
    }

    /** URL of a media file (poster, captions): absolute URLs are checked, paths go through asset() */
    public static function mediaUrl(mixed $value): string
    {
        $value = trim(is_string($value) ? $value : '');

        if ($value === '') {
            return '';
        }

        return preg_match('#^https?://#i', $value) ? HtmlHelper::safeUrl($value) : asset(ltrim($value, '/'));
    }

    /**
     * Add attributes to the first element of a block's HTML (like
     * EditorCanvas::annotate) — never to a leading script or style.
     */
    public static function attachAttributes(string $html, string $attributes): string
    {
        if ($attributes === '') {
            return $html;
        }

        return preg_replace_callback(
            '/^(\s*(?:<!--.*?-->\s*)*<)([a-zA-Z][a-zA-Z0-9-]*)/s',
            fn (array $m) => in_array(strtolower($m[2]), self::NON_CONTENT_TAGS, true) ? $m[0] : $m[0].$attributes,
            $html,
            1,
        ) ?? $html;
    }
}
