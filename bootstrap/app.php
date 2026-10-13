<?php

use App\Http\Middleware\AddCopyrightNotice;
use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\ApplyVisitorTheme;
use App\Http\Middleware\BlockSearchIndexing;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureTwoFactorVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\InjectPluginHooks;
use App\Http\Middleware\LogHttpErrors;
use App\Http\Middleware\MinifyHtml;
use App\Http\Middleware\NegotiateJsonApi;
use App\Http\Middleware\ProtectEmailAddresses;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\ResolveRedirects;
use App\Http\Middleware\ServeIconSprite;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\SetUserLocale;
use App\Http\Middleware\TrustConfiguredHosts;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register role and permission middleware
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'api.auth' => ApiAuthenticate::class,
            'api.json' => ForceJsonResponse::class,
            'api.throttle' => ApiRateLimit::class,
            'install.guard' => RedirectIfInstalled::class,
            'ensure.active' => EnsureUserIsActive::class,
            'set.locale' => SetUserLocale::class,
            'inject.hooks' => InjectPluginHooks::class,
        ]);
        // Maintenance mode bypass cookie (CMS update, System → Updates): Laravel
        // reads it before decryption — it must stay unencrypted
        $middleware->encryptCookies(except: ['laravel_maintenance']);
        // Finished HTML response, from inside out: icons as a sprite,
        // minify, finally the copyright notice. Global and outermost,
        // so it applies after theme and plugins and also reaches error pages.
        $middleware->prepend(ProtectEmailAddresses::class);
        $middleware->prepend(ServeIconSprite::class);
        $middleware->prepend(MinifyHtml::class);
        $middleware->prepend(AddCopyrightNotice::class);
        // Only Host headers from APP_URL / TRUSTED_HOSTS (config/cms.php) — against
        // Host header injection, e.g. poisoned password-reset links
        $middleware->trustHosts();
        $middleware->replace(TrustHosts::class, TrustConfiguredHosts::class);
        // Redirects (Settings → SEO): instead of a 404, also for addresses without a route
        $middleware->append(ResolveRedirects::class);
        $middleware->prependToGroup('api', [
            ForceJsonResponse::class,
            ApiRateLimit::class,
        ]);
        // JSON:API negotiation must run before ForceJsonResponse rewrites the Accept header.
        $middleware->prependToGroup('api', NegotiateJsonApi::class);
        $middleware->prependToGroup('web', RedirectIfNotInstalled::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->appendToGroup('web', EnsureTwoFactorVerified::class);
        $middleware->appendToGroup('web', SetUserLocale::class);
        // A visitor's own theme (profile) for theme:: and plugin view overrides
        $middleware->appendToGroup('web', ApplyVisitorTheme::class);
        $middleware->appendToGroup('web', InjectPluginHooks::class);
        $middleware->appendToGroup('web', SetSecurityHeaders::class);
        // Search engines blocked (Settings → SEO): X-Robots-Tag in the frontend
        $middleware->appendToGroup('web', BlockSearchIndexing::class);
        $middleware->appendToGroup('web', LogHttpErrors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Sanitize unexpected errors for API responses — never leak stack traces
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Let Laravel handle known exception types (validation, auth, model not found, etc.)
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof ModelNotFoundException
                || $e instanceof HttpException) {
                return null;
            }

            // For all other exceptions, return a generic 500 — never expose internals
            return response()->json(['message' => 'Internal server error.'], 500);
        });
    })->create();
