<?php

use App\Support\Dates;

if (! function_exists('format_date')) {
    /** Date in the interface language (or in the format from the profile). */
    function format_date(mixed $value): string
    {
        return Dates::date($value);
    }
}

if (! function_exists('format_datetime')) {
    /** Date and time in the interface language. */
    function format_datetime(mixed $value, bool $seconds = false): string
    {
        return Dates::dateTime($value, $seconds);
    }
}

if (! function_exists('format_date_long')) {
    /** Date with the month written out ("September 24, 2026"). */
    function format_date_long(mixed $value): string
    {
        return Dates::long($value);
    }
}
