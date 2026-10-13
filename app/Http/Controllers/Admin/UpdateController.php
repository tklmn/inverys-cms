<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Updates\CoreUpdater;
use App\Services\Updates\UpdateChecker;
use App\Services\Updates\UpdateException;
use App\Support\CodeAccess;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * System → Updates: new versions of the CMS — download from the release or
 * upload as a ZIP, install, roll back.
 *
 * Plugins and themes show their updates in their own lists
 * (extensions); everything is checked together (cms:check-updates).
 */
#[Middleware('permission:manage-updates')]
class UpdateController extends Controller
{
    public function index(UpdateChecker $checker, CoreUpdater $core): View
    {
        $status = $checker->status()[0];

        return view('backend.updates.index', [
            'core' => $status,
            'package' => $core->releasePackage($status['release']),
            'checkedAt' => $checker->checkedAt(),
            'blocked' => $this->blockedReason($core),
            'backup' => $core->backups()[0] ?? null,
            'pending' => $core->pending(),
            'maxUpload' => $this->maxUploadKb(),
        ]);
    }

    /** Check CMS, plugins and themes now — back to the page the request came from */
    public function check(Request $request, UpdateChecker $checker): RedirectResponse
    {
        // Without a token GitHub allows 60 requests per hour
        $key = 'cms.updates.check.'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->with('error', __('translation.updates.check_throttled'));
        }
        RateLimiter::hit($key, 600);

        $checker->check();

        return back()->with('success', __('translation.updates.checked'));
    }

    /** Download the latest version from the release and install it */
    public function update(Request $request, CoreUpdater $core): Response|RedirectResponse
    {
        if ($response = $this->guard($request, $core)) {
            return $response;
        }

        @set_time_limit(0);

        try {
            $zip = $core->downloadLatest();
            $prepared = $core->prepare($zip);
        } catch (UpdateException $e) {
            return redirect()->route('backend.updates.index')->with('error', $e->getMessage());
        } finally {
            if (isset($zip)) {
                @unlink($zip);
            }
        }

        return $this->start($core, $prepared);
    }

    /** Install a CMS package (ZIP) manually */
    public function upload(Request $request, CoreUpdater $core): Response|RedirectResponse
    {
        if ($response = $this->guard($request, $core)) {
            return $response;
        }

        $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'max:'.$this->maxUploadKb()],
        ]);

        @set_time_limit(0);

        try {
            $prepared = $core->prepare((string) $request->file('package')->getRealPath());
        } catch (UpdateException $e) {
            return redirect()->route('backend.updates.index')->with('error', $e->getMessage());
        }

        return $this->start($core, $prepared);
    }

    /**
     * Step 2 — this request already runs with the new code (despite
     * maintenance mode, thanks to the bypass cookie from step 1).
     */
    public function finish(Request $request, CoreUpdater $core): RedirectResponse
    {
        abort_unless(CodeAccess::allowed($request->user()), 403);

        try {
            $result = $core->finish((string) $request->query('token', ''));
        } catch (UpdateException $e) {
            return redirect()->route('backend.updates.index')->with('error', $e->getMessage());
        }

        return redirect()->route('backend.updates.index')->with(
            $result['status'] === 'updated' ? 'success' : 'error',
            $result['status'] === 'updated'
                ? __('translation.updates.updated', ['name' => 'Inverys CMS', 'version' => $result['version']])
                : __('translation.updates.rolled_back', ['name' => 'Inverys CMS', 'version' => $result['version'], 'error' => $result['error'] ?? '']),
        );
    }

    /** Roll back the last CMS update */
    public function restore(Request $request, CoreUpdater $core): RedirectResponse
    {
        if ($response = $this->guard($request, $core)) {
            return $response;
        }

        try {
            $version = $core->restore();
        } catch (UpdateException $e) {
            return redirect()->route('backend.updates.index')->with('error', $e->getMessage());
        }

        return redirect()->route('backend.updates.index')
            ->with('success', __('translation.updates.restored', ['name' => 'Inverys CMS', 'version' => $version]));
    }

    /**
     * Step 1 and a plain interstitial page that redirects to step 2.
     *
     * The page is plain HTML without Blade: after the swap the new views
     * are on disk while the old code is still in memory. All texts are
     * therefore generated before the swap.
     */
    private function start(CoreUpdater $core, array $prepared): Response|RedirectResponse
    {
        $texts = [];
        foreach (['recovery_title', 'recovery_text', 'recovery_button', 'recovery_done', 'recovery_failed', 'recovery_database'] as $key) {
            $texts[$key] = __('translation.updates.'.$key);
        }
        $title = __('translation.updates.finishing_title', ['version' => $prepared['manifest']['version']]);
        $text = __('translation.updates.finishing_text');
        $continue = __('translation.updates.finishing_continue');
        $help = __('translation.updates.finishing_help');
        $recover = __('translation.updates.recovery_link');
        $lang = str_replace('_', '-', app()->getLocale());

        try {
            $result = $core->install($prepared, $texts);
        } catch (UpdateException $e) {
            return redirect()->route('backend.updates.index')->with('error', $e->getMessage());
        }

        $finish = route('backend.updates.core.finish', ['token' => $result['token']]);
        $recovery = url('update-recovery.php').'?token='.$result['token'];
        $e = fn (string $value) => e($value);
        // Plain like the backend, without its CSS (that already belongs to the new version)
        $style = 'body{margin:0;min-height:100vh;display:grid;place-items:center;font:15px/1.6 system-ui,sans-serif;'
            .'background:#f6f7f9;color:#1b1e27}main{max-width:32rem;margin:1rem;padding:2rem;background:#fff;'
            .'border:1px solid #e3e5ea;border-radius:.75rem}h1{font-size:1.25rem;margin:0 0 .5rem}p{margin:.75rem 0;color:#4b5161}'
            .'a{color:#3b4fd8}.go{display:inline-block;padding:.5rem 1rem;border-radius:.5rem;background:#3b4fd8;color:#fff;text-decoration:none}'
            .'@media (prefers-color-scheme:dark){body{background:#0f1117;color:#e6e8ee}main{background:#171a23;border-color:#2a2f3c}'
            .'p{color:#a7adbb}a{color:#9fb0ff}.go{background:#4c5ee8;color:#fff}}';

        $html = '<!doctype html><html lang="'.$e($lang).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta http-equiv="refresh" content="2;url='.$e($finish).'">'
            .'<title>'.$e($title).'</title><style nonce="'.$e(csp_nonce()).'">'.$style.'</style></head><body><main>'
            .'<h1>'.$e($title).'</h1><p>'.$e($text).'</p>'
            .'<p><a class="go" href="'.$e($finish).'">'.$e($continue).'</a></p>'
            .'<p>'.$e($help).' <a href="'.$e($recovery).'">'.$e($recover).'</a></p>'
            .'</main></body></html>';

        return response($html, 200, ['Cache-Control' => 'no-store'])
            ->withCookie(MaintenanceModeBypassCookie::create($result['secret']));
    }

    /**
     * Code onto the server: admin, CMS_CODE_EDITING, no Git installation,
     * freshly confirmed password.
     */
    private function guard(Request $request, CoreUpdater $core): ?RedirectResponse
    {
        abort_unless(CodeAccess::allowed($request->user()), 403);

        if ($core->isGitCheckout()) {
            return redirect()->route('backend.updates.index')->with('error', __('translation.updates.reason_core_git'));
        }

        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        if (time() - $confirmedAt > (int) config('auth.password_timeout', 10800)) {
            $request->session()->put('url.intended', route('backend.updates.index'));

            return redirect()->route('password.confirm');
        }

        return null;
    }

    /** Why the CMS cannot be updated here, or null */
    private function blockedReason(CoreUpdater $core): ?string
    {
        return CodeAccess::blockedReason(auth()->user())
            ?? ($core->isGitCheckout() ? __('translation.updates.reason_core_git') : null);
    }

    /** Upload size: the CMS limit or PHP's (upload_max_filesize, post_max_size), whichever is smaller */
    private function maxUploadKb(): int
    {
        return (int) min((int) config('cms.updates.max_core_kb', 300 * 1024), floor(UploadedFile::getMaxFilesize() / 1024));
    }
}
