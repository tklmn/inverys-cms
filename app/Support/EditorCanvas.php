<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Markers for the page editor preview (PageController::canvas).
 *
 * Only active there: every block gets data-pb-id on its outermost element
 * (select by click), text spots get data-pb-field (edit directly in the
 * preview). None of this appears on the website.
 *
 *   <h3 class="…" @pbField('title')>{{ $data['title'] }}</h3>
 *   <div class="block-text …" @pbField('content', true)>…</div>   (HTML instead of text)
 */
final class EditorCanvas
{
    /** Blocks without their own entry in the structure — clicks go to the parent block */
    private const UNSELECTABLE = ['column'];

    private static bool $active = false;

    public static function enable(bool $active = true): void
    {
        self::$active = $active;
    }

    public static function active(): bool
    {
        return self::$active;
    }

    public static function field(string $name, bool $rich = false): HtmlString
    {
        if (! self::$active) {
            return new HtmlString('');
        }

        return new HtmlString(' data-pb-field="'.e($name).'"'.($rich ? ' data-pb-rich' : ''));
    }

    /** Attach the block's identifier to the first element of its HTML */
    public static function annotate(string $html, string $id, string $type): string
    {
        if (! self::$active || $id === '' || in_array($type, self::UNSELECTABLE, true)) {
            return $html;
        }

        $attributes = ' data-pb-id="'.e($id).'" data-pb-type="'.e($type).'"';

        // Skip leading whitespace and HTML comments, then the first tag
        return preg_replace('/^(\s*(?:<!--.*?-->\s*)*<[a-zA-Z][a-zA-Z0-9-]*)/s', '$1'.$attributes, $html, 1) ?? $html;
    }
}
