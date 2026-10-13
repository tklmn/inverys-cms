<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Releases of a GitHub repository: query the latest version, download the package.
 *
 * Only GitHub is contacted (also after redirects) — so a package source in the
 * manifest cannot send the server to arbitrary addresses. For private
 * repositories CMS_UPDATE_TOKEN is sent along; on redirects to another host
 * Guzzle drops the Authorization header.
 */
final class GitHubReleases
{
    /** Hosts that release data and packages may come from */
    public const HOSTS = [
        'api.github.com',
        'github.com',
        'codeload.github.com',
        'objects.githubusercontent.com',
        'release-assets.githubusercontent.com',
    ];

    /** "owner/repo" — nothing else is inserted into a URL */
    public static function validRepository(mixed $repository): bool
    {
        return is_string($repository)
            && (bool) preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,38})\/[A-Za-z0-9._-]{1,100}$/', $repository)
            && ! str_contains($repository, '..');
    }

    /**
     * Latest published version (excluding drafts and pre-releases).
     *
     * @return array{version: string, tag: string, name: string, notes: string, url: string, published_at: ?string, download: string}
     */
    public function latest(string $repository): array
    {
        if (! self::validRepository($repository)) {
            throw new UpdateException(__('translation.updates.error_repository', ['repository' => $repository]));
        }

        try {
            $response = $this->client()->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (ConnectionException) {
            throw new UpdateException(__('translation.updates.error_connection'));
        }

        if ($response->status() === 404) {
            throw new UpdateException(__('translation.updates.error_no_release', ['repository' => $repository]));
        }
        if (! $response->ok()) {
            throw new UpdateException(__('translation.updates.error_status', ['status' => $response->status()]));
        }

        $tag = (string) $response->json('tag_name', '');
        $version = ltrim($tag, 'vV');
        if (! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)) {
            throw new UpdateException(__('translation.updates.error_version', ['tag' => $tag]));
        }

        $assets = [];
        foreach ((array) $response->json('assets', []) as $asset) {
            if (! empty($asset['name']) && ! empty($asset['url'])) {
                $assets[(string) $asset['name']] = (string) $asset['url'];
            }
        }

        // An attached ZIP (e.g. with built files) takes precedence over the source archive
        $download = (string) $response->json('zipball_url', '');
        foreach ($assets as $name => $url) {
            if (str_ends_with(strtolower($name), '.zip')) {
                $download = $url;
                break;
            }
        }

        return [
            'version' => $version,
            'tag' => $tag,
            'name' => (string) ($response->json('name') ?: $tag),
            'notes' => mb_substr((string) $response->json('body', ''), 0, 20000),
            'url' => (string) $response->json('html_url', ''),
            'published_at' => $response->json('published_at'),
            'download' => $download,
            'assets' => $assets,
        ];
    }

    /** Download a small text file of a release (e.g. the checksum) */
    public function text(string $url): string
    {
        if (! self::allowedUrl($url)) {
            throw new UpdateException(__('translation.updates.error_download'));
        }

        try {
            $response = $this->client('application/octet-stream')->timeout(30)
                ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['https'], 'on_redirect' => $this->redirectGuard()]])
                ->get($url);
        } catch (ConnectionException) {
            throw new UpdateException(__('translation.updates.error_connection'));
        }

        if (! $response->ok() || strlen($response->body()) > 4096) {
            throw new UpdateException(__('translation.updates.error_download'));
        }

        return $response->body();
    }

    /**
     * Download the release package to $target (ZIP, size-limited).
     *
     * @param  string|null  $url  specific asset; otherwise $release['download']
     */
    public function download(array $release, string $target, ?string $url = null, ?int $maxKb = null): void
    {
        $url ??= (string) ($release['download'] ?? '');
        if (! self::allowedUrl($url)) {
            throw new UpdateException(__('translation.updates.error_download'));
        }

        $maxBytes = ($maxKb ?? (int) config('cms.updates.max_download_kb', 50 * 1024)) * 1024;

        // An attached asset is fetched as a file; the source archive (zipball)
        // refuses that type (415) and redirects to codeload.github.com instead
        $accept = str_contains((string) parse_url($url, PHP_URL_PATH), '/releases/assets/')
            ? 'application/octet-stream'
            : 'application/vnd.github+json';

        try {
            $response = $this->client($accept)
                ->timeout(300)
                ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['https'], 'on_redirect' => $this->redirectGuard()]])
                ->get($url);
        } catch (ConnectionException) {
            throw new UpdateException(__('translation.updates.error_connection'));
        }

        $body = $response->ok() ? $response->body() : '';

        if ($body === '' || strlen($body) > $maxBytes || ! str_starts_with($body, "PK\x03\x04")) {
            throw new UpdateException(__('translation.updates.error_download'));
        }

        file_put_contents($target, $body);
    }

    /** Redirects only to GitHub */
    private function redirectGuard(): callable
    {
        return function (RequestInterface $request, ResponseInterface $response, UriInterface $uri) {
            if (! in_array(strtolower($uri->getHost()), self::HOSTS, true)) {
                throw new UpdateException(__('translation.updates.error_download'));
            }
        };
    }

    public static function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::HOSTS, true);
    }

    private function client(string $accept = 'application/vnd.github+json'): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'Inverys-CMS/'.config('cms.version'),
        ])->timeout(15);

        $token = (string) config('cms.updates.token', '');

        return $token !== '' ? $request->withToken($token) : $request;
    }
}
