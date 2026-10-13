<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;

/**
 * Allowed hosts: the domain from APP_URL including subdomains, plus the hosts
 * from TRUSTED_HOSTS (config/cms.php). Requests with any other Host header end
 * with 400 — otherwise Laravel would build absolute URLs (password reset link,
 * sitemap, robots.txt) from a host chosen by the attacker.
 *
 * No effect before installation (APP_URL still holds the example value
 * then), in the "local" environment and with TRUSTED_HOSTS=*.
 */
class TrustConfiguredHosts extends TrustHosts
{
    /**
     * @return array<int, string>
     */
    public function hosts(): array
    {
        $hosts = array_map(
            fn (string $host) => '^'.preg_quote($host).'$',
            $this->configuredHosts()
        );

        $hosts[] = $this->allSubdomainsOfApplicationUrl();

        // APP_URL with "www.": the domain without "www." is included too
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if (str_starts_with($appHost, 'www.')) {
            $hosts[] = '^'.preg_quote(substr($appHost, 4)).'$';
        }

        return array_values(array_filter($hosts));
    }

    /**
     * @param  \Closure  $next
     */
    public function handle(Request $request, $next)
    {
        // The list is static (Symfony) — explicitly clear it when not checking,
        // so no list from an earlier request in the same process applies
        Request::setTrustedHosts($this->shouldSpecifyTrustedHosts() ? $this->hosts() : []);

        return $next($request);
    }

    protected function shouldSpecifyTrustedHosts(): bool
    {
        if ($this->app->environment('local') || in_array('*', $this->configuredHosts(), true)) {
            return false;
        }

        return file_exists(storage_path('installed'));
    }

    /**
     * Additional hosts without scheme and port, lowercased.
     *
     * @return list<string>
     */
    private function configuredHosts(): array
    {
        $hosts = config('cms.trusted_hosts', []);
        $hosts = is_string($hosts) ? explode(',', $hosts) : (array) $hosts;

        return array_values(array_filter(array_map(
            fn ($host) => strtolower(trim((string) $host)),
            $hosts
        )));
    }
}
