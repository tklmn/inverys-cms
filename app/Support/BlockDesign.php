<?php

declare(strict_types=1);

namespace App\Support;

use App\Helpers\HtmlHelper;

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
     * Background image of a block.
     *
     * @return array{class: string, src: string|null}
     */
    public static function background(array $settings): array
    {
        $image = trim((string) ($settings['bg_image'] ?? ''));

        if ($image === '') {
            return ['class' => '', 'src' => null];
        }

        $overlay = self::OVERLAYS[$settings['bg_overlay'] ?? 'dark'] ?? self::OVERLAYS['dark'];
        $src = preg_match('#^https?://#', $image) ? HtmlHelper::safeUrl($image) : asset(ltrim($image, '/'));

        return ['class' => trim('site-has-bg '.$overlay), 'src' => $src];
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
}
