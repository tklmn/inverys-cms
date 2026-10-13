<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\StorageHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Middleware('permission:show-logs', only: ['index', 'show'])]
#[Middleware('permission:download-logs', only: ['download'])]
#[Middleware('permission:clear-logs', only: ['clear'])]
class LogController extends Controller
{
    public function index(): View
    {
        $logPath = storage_path('logs');
        $logFiles = File::files($logPath);
        $logs = [];

        foreach ($logFiles as $file) {
            if ($file->getExtension() !== 'log') {
                continue;
            }
            $logs[] = [
                'name' => $file->getFilename(),
                'size' => StorageHelper::formatFileSize($file->getSize()),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        usort($logs, fn ($a, $b) => strtotime($b['modified']) - strtotime($a['modified']));

        return view('backend.logs.index', compact('logs'));
    }

    public function show($filename): View|RedirectResponse
    {
        $logPath = $this->resolveLogPath($filename);

        if (! $logPath || ! File::exists($logPath)) {
            return redirect()->route('backend.logs.index')
                ->with('error', __('translation.logs.not_found'));
        }

        $maxLines = 1000;
        $file = new \SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        $truncated = $totalLines > $maxLines;
        $startLine = max(0, $totalLines - $maxLines);
        $file->seek($startLine);

        $content = '';
        while (! $file->eof()) {
            $content .= $file->current();
            $file->next();
        }

        $size = StorageHelper::formatFileSize(File::size($logPath));
        $modified = date('Y-m-d H:i:s', File::lastModified($logPath));

        return view('backend.logs.show', compact('content', 'filename', 'size', 'modified', 'truncated'));
    }

    public function clear($filename): RedirectResponse
    {
        $logPath = $this->resolveLogPath($filename);

        if (! $logPath || ! File::exists($logPath)) {
            return redirect()->route('backend.logs.index')
                ->with('error', __('translation.logs.not_found'));
        }

        File::put($logPath, '');

        return redirect()->route('backend.logs.show', $filename)
            ->with('success', __('translation.logs.cleared'));
    }

    public function download($filename): BinaryFileResponse|RedirectResponse
    {
        $logPath = $this->resolveLogPath($filename);

        if (! $logPath || ! File::exists($logPath)) {
            return redirect()->route('backend.logs.index')
                ->with('error', __('translation.logs.not_found'));
        }

        return response()->download($logPath);
    }

    /**
     * Safely resolve a log filename to a full path, preventing directory traversal.
     */
    private function resolveLogPath(string $filename): ?string
    {
        $sanitized = basename($filename);

        if (! preg_match('/^[\w\-\.]+\.log$/', $sanitized)) {
            return null;
        }

        return storage_path('logs/'.$sanitized);
    }
}
