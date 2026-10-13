<?php

namespace App\Helpers;

class SearchHelper
{
    /**
     * Escape SQL LIKE wildcard characters for safe use in queries.
     *
     * The backslash is escaped first (MySQL's default LIKE escape character).
     * Non-scalar input (e.g. ?search[]=x) becomes an empty string instead of
     * a TypeError.
     */
    public static function escapeLike(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
