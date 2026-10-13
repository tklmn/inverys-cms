<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dates in the UI language.
 *
 * Without a custom setting the language's formats apply (Carbon: L, LT, LL):
 *   en  09/24/2026 · 3:04 PM · September 24, 2026
 *   de  24.09.2026 · 15:04   · 24. September 2026
 *
 * If the user has set a date format in their profile (or the admin the
 * global "date_format" setting), it applies to the date. Month and
 * day names always appear in the current language.
 *
 * In Blade: format_date(), format_datetime(), format_date_long().
 */
final class Dates
{
    public static function date(mixed $value): string
    {
        $date = self::parse($value);
        if (! $date) {
            return '';
        }

        $format = self::customFormat();

        return $format ? $date->translatedFormat($format) : $date->isoFormat('L');
    }

    public static function dateTime(mixed $value, bool $seconds = false): string
    {
        $date = self::parse($value);
        if (! $date) {
            return '';
        }

        return self::date($date).' '.self::time($date, $seconds);
    }

    /**
     * Time matching the date: without a custom format, the language's. With a
     * custom format it follows that — otherwise an English UI with
     * "d.m.Y" would show "24.09.2026 3:04 PM".
     * Month first (m/d/Y) counts as US notation with 12 hours.
     */
    private static function time(CarbonInterface $date, bool $seconds): string
    {
        $format = self::customFormat();
        if ($format === null) {
            return $date->isoFormat($seconds ? 'LTS' : 'LT');
        }

        $twelveHours = in_array(ltrim($format)[0] ?? '', ['m', 'n', 'M', 'F'], true);

        return $date->translatedFormat(($twelveHours ? 'g:i' : 'H:i').($seconds ? ':s' : '').($twelveHours ? ' A' : ''));
    }

    /** With the month spelled out — for the frontend and headings */
    public static function long(mixed $value): string
    {
        $date = self::parse($value);

        return $date ? $date->isoFormat('LL') : '';
    }

    private static function parse(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return self::inUserTimezone($value->copy()->locale(app()->getLocale()));
        }

        if ($value instanceof \DateTimeInterface) {
            return self::inUserTimezone(Carbon::instance($value)->locale(app()->getLocale()));
        }

        // Strings (including plain dates like "2026-09-24") stay in the
        // website's time zone — converting would otherwise shift the day
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->locale(app()->getLocale());
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Show points in time in the profile's time zone. Storage still uses
     * the website's time zone (Settings → General); the profile only
     * changes the display. As with the format: where the frontend sets the
     * visitor's language, the website's time applies to everyone.
     */
    private static function inUserTimezone(CarbonInterface $date): CarbonInterface
    {
        if (app(Locales::class)->visitor() !== null) {
            return $date;
        }

        try {
            // getSetting() caches the value per request; without a custom value
            // the website's time zone is returned — then nothing changes
            $zone = auth()->user()?->getSetting('timezone');
        } catch (\Throwable) {
            return $date;
        }

        if (! is_string($zone) || $zone === '' || ! in_array($zone, timezone_identifiers_list(), true)) {
            return $date;
        }

        return $date->setTimezone($zone);
    }

    /**
     * Custom date format: the signed-in user's profile, otherwise the global
     * setting. In the frontend the language always applies for visitors.
     */
    private static function customFormat(): ?string
    {
        if (app(Locales::class)->visitor() !== null) {
            return null;
        }

        try {
            // Empty profile field = website default, not "no format"
            $format = auth()->user()?->getSetting('date_format');
            if (! is_string($format) || trim($format) === '') {
                $format = setting('date_format');
            }
        } catch (\Throwable) {
            return null;
        }

        return is_string($format) && trim($format) !== '' ? $format : null;
    }
}
