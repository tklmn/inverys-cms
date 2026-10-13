<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\BlockRegistry;
use Illuminate\Support\Str;

/**
 * Difference between two block states of a page, readable instead of JSON:
 * which blocks were added, removed or changed (each with type and a
 * text excerpt) and whether the order has changed.
 * Blocks are matched by their id, including nested ones (columns, sections).
 */
final class BlockDiff
{
    private const EXCERPT = 160;

    /**
     * @return array{added: list<array>, removed: list<array>, changed: list<array>, reordered: bool}|null null = equal
     */
    public static function compare(mixed $before, mixed $after): ?array
    {
        $old = self::flatten(self::decode($before));
        $new = self::flatten(self::decode($after));

        $added = $removed = $changed = [];

        foreach ($new as $id => $block) {
            if (! isset($old[$id])) {
                $added[] = ['label' => self::label($block), 'text' => self::text($block)];
            } elseif (self::content($old[$id]) !== self::content($block)) {
                // Only the texts that differ — otherwise two identical excerpts would be shown
                $was = self::texts($old[$id]);
                $now = self::texts($block);
                $before = array_values(array_diff($was, $now));
                $after = array_values(array_diff($now, $was));
                $changed[] = [
                    'label' => self::label($block),
                    'before' => Str::limit(implode(' · ', $before), self::EXCERPT),
                    'after' => Str::limit(implode(' · ', $after), self::EXCERPT),
                    // No text changed: settings, images, links or appearance
                    'settings_only' => $before === [] && $after === [],
                ];
            }
        }
        foreach ($old as $id => $block) {
            if (! isset($new[$id])) {
                $removed[] = ['label' => self::label($block), 'text' => self::text($block)];
            }
        }

        $common = fn (array $list, array $other) => array_values(array_filter(array_keys($list), fn ($id) => isset($other[$id])));
        $reordered = $common($old, $new) !== $common($new, $old);

        if ($added === [] && $removed === [] && $changed === [] && ! $reordered) {
            return null;
        }

        return compact('added', 'removed', 'changed', 'reordered');
    }

    private static function decode(mixed $blocks): array
    {
        if (is_string($blocks)) {
            $blocks = json_decode($blocks, true);
        }

        return is_array($blocks) ? $blocks : [];
    }

    /**
     * All blocks (including nested ones) in document order, keyed by id.
     *
     * @return array<string, array>
     */
    private static function flatten(array $blocks): array
    {
        $flat = [];
        $walk = function (array $items) use (&$walk, &$flat) {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (isset($item['type'], $item['id']) && is_string($item['type'])) {
                    $flat[(string) $item['id']] = $item;
                }
                foreach ($item as $value) {
                    if (is_array($value)) {
                        $walk($value);
                    }
                }
            }
        };
        $walk($blocks);

        return $flat;
    }

    /** What defines a block, without its children (they count as separate blocks) */
    private static function content(array $block): string
    {
        $strip = function (mixed $value) use (&$strip): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $out = [];
            foreach ($value as $key => $item) {
                if (is_array($item) && isset($item['type'], $item['id'])) {
                    continue;
                }
                $out[$key] = $strip($item);
            }

            return $out;
        };

        return (string) json_encode([$block['type'], $strip($block['data'] ?? []), $block['settings'] ?? []]);
    }

    private static function label(array $block): string
    {
        $name = app(BlockRegistry::class)->get($block['type'])['name'] ?? null;

        return $name ? (string) __($name) : Str::headline($block['type']);
    }

    /** Fields with technical values instead of text (URLs, appearance) */
    private const TECHNICAL = '/^(id|url|href|link|target|rel|icon|icons|image|images|src|media|media_id|video|level|align|alignment|style|variant|size|width|height|color|colour|background|class|anchor|layout|columns|tone|position|ratio|format|type)$/i';

    /** Readable excerpt of the block's texts (without children, without URLs) */
    private static function text(array $block): string
    {
        return Str::limit(implode(' · ', self::texts($block)), self::EXCERPT);
    }

    /** @return list<string> */
    private static function texts(array $block): array
    {
        $parts = [];
        $collect = function (mixed $value, int|string $key = '') use (&$collect, &$parts) {
            if (is_string($key) && preg_match(self::TECHNICAL, $key)) {
                return;
            }
            if (is_string($value)) {
                $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($plain !== '' && ! preg_match('#^(https?://|/|\#)#', $plain)) {
                    $parts[] = $plain;
                }

                return;
            }
            if (is_array($value) && ! isset($value['type'], $value['id'])) {
                foreach ($value as $itemKey => $item) {
                    $collect($item, $itemKey);
                }
            }
        };
        $collect($block['data'] ?? []);

        return array_values(array_unique($parts));
    }
}
