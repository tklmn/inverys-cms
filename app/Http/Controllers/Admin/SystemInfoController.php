<?php

namespace App\Http\Controllers\Admin;

use App\Support\Sbom\CycloneDx;
use App\Support\Sbom\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

#[Middleware('auth')]
#[Middleware('permission:show-system')]
class SystemInfoController extends Controller
{
    /** Media type for CycloneDX JSON (IANA) */
    private const CYCLONEDX_TYPE = 'application/vnd.cyclonedx+json';

    public function show()
    {
        $dbVersion = null;
        try {
            $result = DB::select('select version() as version');
            $dbVersion = $result[0]->version ?? null;
        } catch (\Throwable $e) {
            $dbVersion = 'N/A';
        }

        $info = [
            'cms_version' => config('cms.version'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'database_connection' => config('database.default'),
            'database_version' => $dbVersion,
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'cache_driver' => config('cache.default'),
            'storage_disk' => config('filesystems.default'),
            'log_channel' => config('logging.default'),
            'server_time' => now()->toDateTimeString(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'php_extensions' => get_loaded_extensions(),
            'os' => php_uname(),
            'webserver' => $_SERVER['SERVER_SOFTWARE'] ?? '-',
        ];

        return view('backend.system.info', compact('info'));
    }

    /**
     * Components (SBOM): what is installed on this server.
     *
     * Filters via the address (?kind=plugin&dev=1&q=…), so a link reopens
     * exactly this view; the search additionally filters instantly
     * in the browser (data-filter-input).
     */
    public function components(Request $request, Inventory $inventory): View
    {
        $all = $inventory->components();
        $summary = $inventory->summary();

        $kind = in_array($request->query('kind'), Inventory::KINDS, true) ? $request->query('kind') : null;
        // Development packages only on request — or when explicitly selected
        $withDev = $request->boolean('dev') || $kind === Inventory::KIND_DEV;
        $search = Str::lower(trim((string) $request->query('q', '')));

        $components = array_values(array_filter($all, function (array $item) use ($kind, $withDev, $search) {
            if ($kind !== null && $item['kind'] !== $kind) {
                return false;
            }
            if (! $withDev && $item['kind'] === Inventory::KIND_DEV) {
                return false;
            }

            return $search === '' || str_contains(Inventory::searchText($item), $search);
        }));

        return view('backend.system.components', [
            'components' => $components,
            'summary' => $summary,
            'releases' => $inventory->releaseSboms(),
            'filters' => ['kind' => $kind, 'dev' => $withDev, 'q' => (string) $request->query('q', '')],
            'generatedAt' => now(),
        ]);
    }

    /** CycloneDX SBOM of this installation, generated fresh on every request */
    public function downloadSbom(Request $request, Inventory $inventory): Response
    {
        $host = $request->getHost();

        return response(CycloneDx::encode($inventory->cycloneDx($host)), 200, [
            'Content-Type' => self::CYCLONEDX_TYPE.'; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', Inventory::fileName($host)),
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Bundled release SBOM (sbom/composer.cdx.json or sbom/npm.cdx.json) */
    public function downloadRelease(Inventory $inventory, string $file): BinaryFileResponse
    {
        $release = $inventory->releaseSboms()[$file] ?? null;
        abort_unless($release && $release['exists'], 404);

        // Same name as the attachment on the GitHub release (bin/release.sh)
        return response()->download($release['path'], 'inverys-cms-'.$release['version'].'.'.$file.'.cdx.json', [
            'Content-Type' => self::CYCLONEDX_TYPE,
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        return redirect()->route('backend.system.info')->with('success', __('translation.system.cache.cleared'));
    }
}
