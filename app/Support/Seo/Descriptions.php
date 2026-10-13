<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Meta descriptions: the first usable candidate, as plain text on one line,
 * shortened at a word boundary to what search engines show (~160 characters).
 * Without any candidate: the site's default description (Settings → SEO).
 */
final class Descriptions
{
    public const MAX_LENGTH = 160;

    /** Email address — the same pattern as App\Support\EmailProtector */
    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/';

    public static function pick(mixed ...$candidates): ?string
    {
        $hideEmails = (string) setting('html.protect_emails', '1') !== '0';

        foreach ([...$candidates, setting('seo.default_description', '')] as $candidate) {
            $text = self::plain(is_string($candidate) ? $candidate : null);

            // Email protection (Settings → General) also covers the head: a text
            // with an address is skipped, the address would be readable there
            if ($text === '' || ($hideEmails && preg_match(self::EMAIL, $text))) {
                continue;
            }

            return self::shorten($text);
        }

        return null;
    }

    /** HTML removed, entities decoded, whitespace collapsed */
    public static function plain(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }

    public static function shorten(string $text, int $limit = self::MAX_LENGTH): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');

        // Cut at the last word boundary, unless that throws away too much
        if ($space !== false && $space > $limit * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t,.;:–-").'…';
    }
}
