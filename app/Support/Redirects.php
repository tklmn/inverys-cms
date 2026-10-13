<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\ContentTranslator;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Redirects from old addresses (Settings → SEO → Redirects).
 *
 * Paths are stored without language prefix ("/old-page"); a redirect with
 * a locale only applies in that content language (translated addresses).
 * They are only looked up when a request would end in 404 — a live page
 * always wins (App\Http\Middleware\ResolveRedirects).
 *
 * When the address of a page or post changes, a 301 is created
 * automatically (recordMove()): chains are shortened (A → B, B → C becomes
 * A → C, B → C), and a redirect away from an address that is live again
 * is removed, so no loops arise.
 */
final class Redirects
{
    /** Maximum chain length that is followed when resolving */
    public const MAX_HOPS = 10;

    /** Longest stored source path */
    public const MAX_PATH = 255;

    /**
     * Is the table there (after the migration)? Checked once per
     * application — not static, so a fresh app (tests, Octane) asks again.
     */
    public static function enabled(): bool
    {
        $app = app();

        if (! $app->bound(self::class.'.enabled')) {
            try {
                $enabled = Schema::hasTable('redirects');
            } catch (\Throwable) {
                $enabled = false;
            }
            $app->instance(self::class.'.enabled', $enabled);
        }

        return (bool) $app->make(self::class.'.enabled');
    }

    /**
     * Path on this site as stored: leading "/", no trailing "/", no query
     * or fragment, no duplicate slashes. Addresses of this site are reduced
     * to their path.
     */
    public static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);
        $path = self::sitePath($path) ?? $path;
        $path = (string) parse_url($path, PHP_URL_PATH);
        $path = '/'.trim((string) preg_replace('#/+#', '/', $path), '/');

        return $path;
    }

    /** Target as stored: a path of this site (with query) or an absolute http(s) address */
    public static function normalizeTarget(?string $target): string
    {
        $target = trim((string) $target);
        $sitePath = self::sitePath($target);

        if ($sitePath !== null) {
            $target = $sitePath;
        }

        if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            $query = parse_url($target, PHP_URL_QUERY);

            return self::normalizePath($target).(is_string($query) && $query !== '' ? '?'.$query : '');
        }

        return $target;
    }

    public static function isInternal(string $target): bool
    {
        return str_starts_with($target, '/') && ! str_starts_with($target, '//');
    }

    /**
     * Final target for a requested path: [redirect, target, status] —
     * following chains, null if there is none or the chain runs in a circle.
     *
     * @return array{redirect: Redirect, target: string, status: int}|null
     */
    public static function resolve(string $path, ?string $locale): ?array
    {
        $path = self::normalizePath($path);
        $first = self::find($path, $locale);

        if ($first === null) {
            return null;
        }

        $target = $first->target;
        $seen = [mb_strtolower($path) => true];

        for ($hop = 0; $hop < self::MAX_HOPS && self::isInternal($target); $hop++) {
            $next = self::normalizePath($target);

            if (isset($seen[mb_strtolower($next)])) {
                // Leads back to an address already visited: no redirect at all
                return null;
            }
            $seen[mb_strtolower($next)] = true;

            $redirect = self::find($next, $locale);
            if ($redirect === null) {
                break;
            }
            $target = $redirect->target;
        }

        return ['redirect' => $first, 'target' => $target, 'status' => $first->status_code];
    }

    /** Redirect for exactly this path: the one of the language first, then the general one; case-insensitive as a fallback */
    public static function find(string $path, ?string $locale): ?Redirect
    {
        $candidates = Redirect::query()
            ->where(fn ($q) => $q->where('source_path', $path)->orWhereRaw('LOWER(source_path) = ?', [mb_strtolower($path)]))
            ->where(fn ($q) => $q->whereNull('locale')->when($locale !== null, fn ($q) => $q->orWhere('locale', $locale)))
            ->get();

        return $candidates->sortBy(fn (Redirect $r) => [
            $r->source_path === $path ? 0 : 1,
            $r->locale !== null ? 0 : 1,
            $r->id,
        ])->first();
    }

    /**
     * Would this redirect create a loop (it leads, directly or via other
     * redirects, back to its own source)?
     */
    public static function wouldLoop(string $source, string $target, ?string $locale, ?int $ignoreId = null): bool
    {
        $source = self::normalizePath($source);
        $target = self::normalizeTarget($target);

        if (! self::isInternal($target)) {
            return false;
        }

        $seen = [];
        for ($hop = 0; $hop <= self::MAX_HOPS && self::isInternal($target); $hop++) {
            $path = self::normalizePath($target);

            if (mb_strtolower($path) === mb_strtolower($source) || isset($seen[$path])) {
                return true;
            }
            $seen[$path] = true;

            $next = self::find($path, $locale);
            if ($next === null || $next->id === $ignoreId) {
                return false;
            }
            $target = $next->target;
        }

        return true;
    }

    /**
     * An address has moved (slug changed): old path → new path with 301.
     * Chains are shortened, redirects away from the new address removed.
     */
    public static function recordMove(string $from, string $to, ?string $locale = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $from = self::normalizePath($from);
        $to = self::normalizePath($to);

        if ($from === $to || $from === '/' || mb_strlen($from) > self::MAX_PATH) {
            return;
        }

        $scope = fn ($q) => $locale === null ? $q->whereNull('locale') : $q->where('locale', $locale);

        // The new address is live (again): no redirect away from it
        Redirect::query()->where('source_path', $to)->where($scope)->delete();

        // Shorten chains: whatever led to the old address now leads to the new one
        Redirect::query()->where('target', $from)->where($scope)->update(['target' => $to, 'updated_at' => now()]);

        $redirect = Redirect::query()->where('source_path', $from)->where($scope)->first() ?? new Redirect;
        $redirect->fill([
            'source_path' => $from,
            'locale' => $locale,
            'target' => $to,
            'status_code' => 301,
            'is_automatic' => true,
        ])->save();
    }

    /** A model's slug changed: redirect from the old to the new address (Locales::pathUsing) */
    public static function recordSlugChange(Model $model, ?string $oldSlug, ?string $newSlug, ?string $locale = null): void
    {
        $oldSlug = trim((string) $oldSlug);
        $newSlug = trim((string) $newSlug);

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug || $oldSlug === '/') {
            return;
        }

        try {
            $locales = app(Locales::class);
            $from = $locales->pathFor($model, $oldSlug);
            $to = $locales->pathFor($model, $newSlug);

            if ($from !== null && $to !== null) {
                self::recordMove($from, $to, $locale);
            }
        } catch (\Throwable $e) {
            // A missing redirect must never prevent saving
            report($e);
        }
    }

    /**
     * The slug a model has in one language — with the same fallback chain as
     * the frontend (language, default language, original).
     */
    public static function effectiveSlug(Model $model, string $locale): string
    {
        $translator = app(ContentTranslator::class);

        foreach (app(Locales::class)->contentChain($locale) as $code) {
            $slug = $translator->get($model, 'slug', $code);
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        return (string) (method_exists($model, 'getOriginalValue') ? $model->getOriginalValue('slug') : $model->getAttribute('slug'));
    }

    /** Path of an address of this site (with the request's base URL or APP_URL), otherwise null */
    private static function sitePath(string $value): ?string
    {
        if (! preg_match('#^https?://#i', $value)) {
            return null;
        }

        $roots = [rtrim((string) config('app.url'), '/')];
        if (app()->bound('request') && ! app()->runningInConsole()) {
            $request = request();
            $roots[] = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
        }

        foreach (array_filter(array_unique($roots)) as $root) {
            if (strcasecmp($value, $root) === 0) {
                return '/';
            }
            if (stripos($value, $root.'/') === 0 || stripos($value, $root.'?') === 0) {
                return '/'.ltrim(substr($value, strlen($root)), '/');
            }
        }

        return null;
    }
}
