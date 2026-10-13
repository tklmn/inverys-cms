<?php

namespace Plugins\Multilingual\Support;

/**
 * Which URL areas are content (and get a language prefix)?
 *
 * Only pages, home page, blog, search and form submissions. Backend,
 * API, login, profile and files stay without a prefix — they have no
 * content language, and their URLs should remain stable.
 */
final class Paths
{
    /** First path segments that are never content */
    private const EXCLUDED = ['ext-assets',
        'api', 'install', 'livewire', 'storage', 'build', 'vendor', 'protected-assets', 'up',
        'theme', 'captcha', 'login', 'logout', 'register', 'forgot-password', 'reset-password',
        'verify-email', 'confirm-password', 'two-factor-challenge', 'two-factor-resend',
        'email', 'password', 'mail-providers', 'profile', 'css', 'js', 'images', 'favicon.ico',
    ];

    public static function isContent(string $firstSegment): bool
    {
        $backend = trim((string) config('cms.backend_prefix', 'backend'), '/') ?: 'backend';

        if ($firstSegment === $backend) {
            return false;
        }

        // Files (robots.txt, sitemap.xml …) keep their URL
        if (str_contains($firstSegment, '.')) {
            return false;
        }

        return ! in_array($firstSegment, self::EXCLUDED, true);
    }
}
