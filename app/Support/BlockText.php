<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Readable text of a block layout — for search and short descriptions.
 *
 * Pages consist only of blocks now; but search and the description in
 * search results read the "content" column. So on save this text is
 * stored there: all text fields of the blocks, HTML removed,
 * URLs, images, icons and technical values left out.
 */
final class BlockText
{
    /** Fields without readable text (URLs, images, style keys …) */
    private const SKIP = [
        'url', 'src', 'image', 'poster', 'bg_image', 'icon', 'style', 'type', 'level',
        'alignment', 'target', 'language', 'primary_url', 'secondary_url', 'button_url',
        'interval', 'autoplay', 'show_arrows', 'show_dots', 'featured', 'form_id', 'code',
    ];

    public static function extract(?array $blocks): string
    {
        $parts = [];
        self::collect($blocks ?? [], $parts);

        $text = preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '';

        return trim($text);
    }

    /** @param list<string> $parts */
    private static function collect(array $blocks, array &$parts): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            self::values($block['data'] ?? [], $parts);

            if (! empty($block['children']) && is_array($block['children'])) {
                self::collect($block['children'], $parts);
            }
        }
    }

    /** @param list<string> $parts */
    private static function values(mixed $data, array &$parts): void
    {
        if (! is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::SKIP, true)) {
                continue;
            }

            if (is_array($value)) {
                self::values($value, $parts);
            } elseif (is_string($value) && trim($value) !== '') {
                // Do not glue paragraphs and lines together
                $plain = strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], $value));
                $parts[] = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }
}
