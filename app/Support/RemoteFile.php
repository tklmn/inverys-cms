<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Updates\UpdateException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Downloads from addresses an administrator entered: marketplace catalogs,
 * their packages and preview images.
 *
 *   - https only, no credentials in the address, size-limited
 *   - public hosts only: the server must not become a way into its own
 *     network (127.0.0.1, 10.x, 192.168.x, cloud metadata at 169.254.x …)
 *   - the host is resolved once and the connection pinned to that address,
 *     so DNS cannot switch to an internal one between check and request
 *   - redirects are followed by hand, every hop checked the same way
 *
 * cms.marketplace.allow_private_hosts lifts the host check for a
 * marketplace inside a company network.
 */
class RemoteFile
{
    private const MAX_REDIRECTS = 5;

    /** @param  (Closure(string): list<string>)|null  $resolver  host → IP addresses (replaceable in tests) */
    public function __construct(private readonly ?Closure $resolver = null) {}

    /** https, a host name, no user name or password, default port */
    public static function validUrl(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 2048) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && (($parts['port'] ?? 443) === 443);
    }

    /**
     * The body of $url, at most $maxBytes long.
     *
     * @throws UpdateException
     */
    public function get(string $url, int $maxBytes, string $accept = '*/*'): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! self::validUrl($url)) {
                throw new UpdateException(__('translation.marketplace.error_address', ['url' => $url]));
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $address = $this->publicAddress($host);

            try {
                $response = Http::withHeaders([
                    'Accept' => $accept,
                    'User-Agent' => 'Inverys-CMS/'.config('cms.version'),
                ])
                    ->timeout(60)
                    ->withOptions([
                        'allow_redirects' => false,
                        // Connect to exactly the address that was checked
                        'curl' => [CURLOPT_RESOLVE => [trim($host, '[]').':443:'.(str_contains($address, ':') ? '['.$address.']' : $address)]],
                    ])
                    ->get($url);
            } catch (ConnectionException) {
                throw new UpdateException(__('translation.marketplace.error_connection', ['host' => $host]));
            }

            if ($response->redirect() && $response->header('Location') !== '') {
                $url = $this->absolute($url, $response->header('Location'));

                continue;
            }

            if (! $response->ok()) {
                throw new UpdateException(__('translation.marketplace.error_status', ['host' => $host, 'status' => $response->status()]));
            }

            $body = $response->body();
            if (strlen($body) > $maxBytes) {
                throw new UpdateException(__('translation.marketplace.error_size', ['host' => $host]));
            }

            return $body;
        }

        throw new UpdateException(__('translation.marketplace.error_redirects', ['url' => $url]));
    }

    /** A public IP address of the host, or an exception */
    private function publicAddress(string $host): string
    {
        $literal = trim($host, '[]');
        $addresses = filter_var($literal, FILTER_VALIDATE_IP) ? [$literal] : $this->resolve($host);

        if ($addresses === []) {
            throw new UpdateException(__('translation.marketplace.error_connection', ['host' => $host]));
        }

        if (! config('cms.marketplace.allow_private_hosts', false)) {
            foreach ($addresses as $address) {
                if (! self::isPublicIp($address)) {
                    throw new UpdateException(__('translation.marketplace.error_private', ['host' => $host]));
                }
            }
        }

        return $addresses[0];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        // Shared address space (carrier-grade NAT) and IPv4-mapped IPv6 are not covered by the flags
        if (str_contains($ip, '.') && ! str_contains($ip, ':')) {
            $long = ip2long($ip);

            return ! ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'));
        }

        return ! str_starts_with(strtolower($ip), '::ffff:');
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_merge(array_column($records, 'ip'), array_column($records, 'ipv6')));
    }

    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = 'https://'.$parts['host'];

        return str_starts_with($location, '/')
            ? $origin.$location
            : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }
}
