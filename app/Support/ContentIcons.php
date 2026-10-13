<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Icons that editors can choose in content (cards, buttons,
 * hero, key figures). A fixed, curated selection instead of all 1,900 Lucide
 * icons: easy to browse in the page builder and small in the package.
 *
 * Add new icons here AND in copy-icons.cjs, then run `node copy-icons.cjs`.
 */
final class ContentIcons
{
    public const NAMES = [
        // Product & technology
        'zap', 'rocket', 'sparkles', 'wand-sparkles', 'layers', 'blocks', 'layout-dashboard', 'palette',
        'puzzle', 'image', 'code', 'terminal', 'database', 'server', 'cloud', 'workflow',
        'monitor-smartphone', 'smartphone', 'gauge', 'infinity',
        // Security & people
        'shield-check', 'lock', 'users', 'user', 'handshake', 'heart', 'star', 'award',
        // Content & knowledge
        'file-text', 'book-open', 'pen-tool', 'lightbulb', 'target', 'chart-column', 'trending-up',
        'search', 'globe', 'languages', 'leaf', 'gift', 'briefcase', 'building-2',
        // Contact & organization
        'message-square', 'mail', 'phone', 'map-pin', 'clock', 'calendar', 'headphones', 'life-buoy',
        'settings', 'check', 'circle-check', 'arrow-right',
    ];

    public static function has(?string $name): bool
    {
        return is_string($name) && in_array($name, self::NAMES, true);
    }
}
