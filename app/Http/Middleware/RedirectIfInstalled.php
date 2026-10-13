<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->markerExists()) {
            return redirect(url('/'));
        }

        // Marker missing, but the database already has users and no
        // installation is running (e.g. storage/ not carried over after a
        // deployment): do NOT unlock the installer — otherwise any visitor
        // could set up the site again and make themselves admin.
        if (! $this->installInProgress() && $this->databaseHasUsers()) {
            return response(
                'Inverys CMS is already installed, but the file storage/installed is missing. '
                .'Restore it (or recreate it) to bring the site back online; the web installer stays locked.',
                403,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        return $next($request);
    }

    protected function markerExists(): bool
    {
        return file_exists(storage_path('installed'));
    }

    /** An installation in progress (the wizard's state file) may continue */
    protected function installInProgress(): bool
    {
        return file_exists(storage_path('framework/install-state.json'));
    }

    protected function databaseHasUsers(): bool
    {
        try {
            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (\Throwable) {
            // No (reachable) database: not installed yet
            return false;
        }
    }
}
