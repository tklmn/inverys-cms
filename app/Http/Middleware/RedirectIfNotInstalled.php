<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! file_exists(storage_path('installed'))) {
            // Force file-based session/cache so the app works without DB tables
            config(['session.driver' => 'file', 'cache.default' => 'file']);

            // Some archives lack empty folders — without them even the session fails
            $this->ensureStorageDirectories();

            // Auto-create .env from .env.example if missing
            $this->ensureEnvFile();

            // Generate a temporary APP_KEY if missing (needed for cookie encryption)
            $this->ensureAppKey();

            // Don't redirect installer routes themselves
            if (! $request->is('install*')) {
                return redirect('/install');
            }

            return $next($request);
        }

        // Upload via FTP: empty folders and symlinks often don't make it —
        // without storage/framework/views there are no pages, without public/storage no images
        $this->ensureStorageDirectories();
        $this->ensureStorageLink();

        return $next($request);
    }

    /** Link public/storage relatively to storage/app/public if the link is missing */
    private function ensureStorageLink(): void
    {
        $link = public_path('storage');
        if (PHP_OS_FAMILY === 'Windows' || file_exists($link) || is_link($link) || ! is_dir(storage_path('app/public'))) {
            return;
        }

        @symlink('../storage/app/public', $link);
    }

    private function ensureStorageDirectories(): void
    {
        foreach (['framework/sessions', 'framework/cache/data', 'framework/views', 'logs', 'app/public'] as $dir) {
            if (! is_dir(storage_path($dir))) {
                @mkdir(storage_path($dir), 0775, true);
            }
        }
    }

    private function ensureEnvFile(): void
    {
        if (file_exists(base_path('.env'))) {
            return;
        }

        // The installer is publicly reachable — until it writes the final .env,
        // no error pages with stack traces and environment data
        // (.env.example is meant for developers and has APP_DEBUG=true)
        $env = file_exists(base_path('.env.example'))
            ? (string) file_get_contents(base_path('.env.example'))
            : "APP_NAME=Laravel\nAPP_KEY=\nAPP_ENV=local\n";
        $env = preg_match('/^APP_DEBUG=.*$/m', $env)
            ? preg_replace('/^APP_DEBUG=.*$/m', 'APP_DEBUG=false', $env)
            : $env."APP_DEBUG=false\n";

        file_put_contents(base_path('.env'), $env);
        config(['app.debug' => false]);
    }

    private function ensureAppKey(): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);

        // Persist to .env so subsequent requests use the same key
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $env = file_get_contents($envPath);
            if (preg_match('/^APP_KEY=.*$/m', $env)) {
                $env = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $env);
            } else {
                $env .= "\nAPP_KEY=".$key."\n";
            }
            file_put_contents($envPath, $env);
        }
    }
}
