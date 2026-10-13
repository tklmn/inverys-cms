<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ThemeManager;
use App\Support\CodeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

#[Middleware('permission:edit-theme-files')]
// Before editing code: confirm the password again (valid for a few hours)
#[Middleware('password.confirm', only: ['index'])]
class ThemeEditorController extends Controller
{
    public function index(string $slug, ThemeManager $themeManager): View|RedirectResponse
    {
        // Turned off (CMS_CODE_EDITING=false): no editor, not even read-only
        abort_unless(CodeAccess::enabled(), 403, __('translation.code_access.disabled'));

        $theme = $themeManager->get($slug);
        if (! $theme) {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('Theme not found.')]);
        }

        if (! $themeManager->isEditable($slug)) {
            return redirect()->route('backend.themes.index')
                ->withErrors(['slug' => __('translation.theme_editor.not_editable')]);
        }

        $files = $themeManager->getEditableFiles($slug);
        $fileTree = $this->buildFileTree($files);

        return view('backend.themes.editor', [
            'theme' => $theme,
            'files' => $files,
            'fileTree' => $fileTree,
        ]);
    }

    public function loadFile(Request $request, string $slug, ThemeManager $themeManager): JsonResponse
    {
        // Turned off (CMS_CODE_EDITING=false): no editor, not even read-only
        abort_unless(CodeAccess::enabled(), 403, __('translation.code_access.disabled'));

        $path = $request->query('path');
        if (! $path || ! is_string($path)) {
            return response()->json(['error' => __('translation.theme_editor.invalid_path')], 422);
        }

        $validation = $themeManager->validateFilePath($slug, $path);
        if (! $validation['valid']) {
            return response()->json([
                'error' => __('translation.theme_editor.'.$validation['error']),
            ], 403);
        }

        $absolutePath = $validation['absolute_path'];

        // Reject files larger than 500 KB
        if (filesize($absolutePath) > 512000) {
            return response()->json(['error' => __('translation.theme_editor.file_too_large')], 422);
        }

        $content = File::get($absolutePath);

        return response()->json([
            'content' => $content,
            'language' => $this->detectLanguage($path),
            'has_original' => file_exists($absolutePath.'.orig'),
        ]);
    }

    public function saveFile(Request $request, string $slug, ThemeManager $themeManager): JsonResponse
    {
        // Turned off (CMS_CODE_EDITING=false): no editor, not even read-only
        abort_unless(CodeAccess::enabled(), 403, __('translation.code_access.disabled'));

        $request->validate([
            'path' => 'required|string',
            'content' => 'present|string',
        ]);

        $path = $request->input('path');

        $validation = $themeManager->validateFilePath($slug, $path);
        if (! $validation['valid']) {
            return response()->json([
                'error' => __('translation.theme_editor.'.$validation['error']),
            ], 403);
        }

        $absolutePath = $validation['absolute_path'];

        if (! is_writable($absolutePath)) {
            return response()->json([
                'error' => __('translation.theme_editor.file_not_writable'),
            ], 403);
        }

        // Create .orig on first edit (for reset functionality)
        $origPath = $absolutePath.'.orig';
        if (! file_exists($origPath)) {
            File::copy($absolutePath, $origPath);
        }

        // Create .bak backup before saving
        File::copy($absolutePath, $absolutePath.'.bak');

        File::put($absolutePath, $request->input('content'));

        // Clear compiled view cache so changes take effect immediately
        Artisan::call('view:clear');

        return response()->json([
            'ok' => true,
            'message' => __('translation.theme_editor.saved'),
        ]);
    }

    public function resetFile(Request $request, string $slug, ThemeManager $themeManager): JsonResponse
    {
        // Turned off (CMS_CODE_EDITING=false): no editor, not even read-only
        abort_unless(CodeAccess::enabled(), 403, __('translation.code_access.disabled'));

        $request->validate(['path' => 'required|string']);

        $path = $request->input('path');

        $validation = $themeManager->validateFilePath($slug, $path);
        if (! $validation['valid']) {
            return response()->json([
                'error' => __('translation.theme_editor.'.$validation['error']),
            ], 403);
        }

        $absolutePath = $validation['absolute_path'];
        $origPath = $absolutePath.'.orig';

        if (! file_exists($origPath)) {
            return response()->json([
                'error' => __('translation.theme_editor.no_original'),
            ], 404);
        }

        File::copy($origPath, $absolutePath);

        Artisan::call('view:clear');

        $content = File::get($absolutePath);

        return response()->json([
            'ok' => true,
            'content' => $content,
            'message' => __('translation.theme_editor.file_reset'),
        ]);
    }

    /**
     * Build a nested tree structure from flat file paths.
     */
    protected function buildFileTree(array $files): array
    {
        $tree = [];

        foreach ($files as $file) {
            $parts = explode('/', $file);
            $current = &$tree;

            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    // File
                    $current[] = ['name' => $part, 'path' => $file, 'type' => 'file'];
                } else {
                    // Directory — find or create
                    $found = false;
                    foreach ($current as &$node) {
                        if (($node['type'] ?? '') === 'dir' && $node['name'] === $part) {
                            $current = &$node['children'];
                            $found = true;
                            break;
                        }
                    }
                    unset($node);

                    if (! $found) {
                        $current[] = ['name' => $part, 'type' => 'dir', 'children' => []];
                        $current = &$current[count($current) - 1]['children'];
                    }
                }
            }

            unset($current);
        }

        return $tree;
    }

    protected function detectLanguage(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) {
            return 'blade';
        }

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'php' => 'php',
            'css', 'scss', 'less' => 'css',
            'js' => 'javascript',
            'json' => 'json',
            'html' => 'html',
            'xml', 'svg' => 'xml',
            'yaml', 'yml' => 'yaml',
            'md' => 'markdown',
            default => 'text',
        };
    }
}
