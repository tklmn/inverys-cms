<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use App\Support\Locales;
use App\Support\Redirects;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → SEO → Redirects: old addresses that lead to new ones.
 *
 * Entered by hand here; changed slugs of pages and posts add theirs
 * automatically (App\Support\Redirects::recordMove). Same permission as
 * the SEO settings.
 */
#[Middleware('auth')]
#[Middleware('permission:manage-seo-settings')]
class RedirectController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $search = is_string($request->input('q')) ? trim($request->input('q')) : '';

        $redirects = Redirect::query()
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['=', '%', '_'], ['==', '=%', '=_'], $search).'%';
                $query->where(fn ($q) => $q->whereRaw("source_path LIKE ? ESCAPE '='", [$like])->orWhereRaw("target LIKE ? ESCAPE '='", [$like]));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $editing = $request->filled('edit') ? Redirect::find((int) $request->input('edit')) : null;
        $locales = app(Locales::class);

        return view('backend.settings.redirects', [
            'redirects' => $redirects,
            'editing' => $editing,
            'search' => $search,
            'locales' => $locales->isMultilingual() ? $locales->all() : [],
            'statusCodes' => Redirect::STATUS_CODES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Redirect::create($this->validated($request));

        return redirect()->route('backend.settings.seo.redirects')->with('success', __('translation.redirects.created'));
    }

    public function update(Request $request, Redirect $redirect): RedirectResponse
    {
        // Edited by hand: it no longer counts as automatic
        $redirect->fill($this->validated($request, $redirect) + ['is_automatic' => false])->save();

        return redirect()->route('backend.settings.seo.redirects')->with('success', __('translation.redirects.updated'));
    }

    public function destroy(Redirect $redirect): RedirectResponse
    {
        $redirect->delete();

        return redirect()->route('backend.settings.seo.redirects')->with('success', __('translation.redirects.deleted'));
    }

    /** @return array{source_path: string, target: string, status_code: int, locale: ?string} */
    private function validated(Request $request, ?Redirect $current = null): array
    {
        $locales = app(Locales::class);

        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:'.Redirects::MAX_PATH, $this->sourceRule($request, $current)],
            'target' => ['required', 'string', 'max:2000', $this->targetRule()],
            'status_code' => ['required', 'integer', Rule::in(Redirect::STATUS_CODES)],
            'locale' => ['nullable', 'string', Rule::in($locales->codes())],
        ], [], [
            'source_path' => __('translation.redirects.source'),
            'target' => __('translation.redirects.target'),
            'status_code' => __('translation.redirects.status'),
            'locale' => __('translation.redirects.language'),
        ]);

        return [
            'source_path' => Redirects::normalizePath($data['source_path']),
            'target' => Redirects::normalizeTarget($data['target']),
            'status_code' => (int) $data['status_code'],
            'locale' => $data['locale'] ?? null,
        ];
    }

    /** Not the home page, not taken yet, no loop */
    private function sourceRule(Request $request, ?Redirect $current): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request, $current) {
            $path = Redirects::normalizePath(is_string($value) ? $value : '');
            $locale = $request->input('locale') ?: null;

            if ($path === '/') {
                $fail(__('translation.redirects.source_home'));

                return;
            }

            $taken = Redirect::query()
                ->where('source_path', $path)
                ->where(fn ($q) => $locale === null ? $q->whereNull('locale') : $q->where('locale', $locale))
                ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('translation.redirects.source_taken'));

                return;
            }

            $target = $request->input('target');
            if (is_string($target) && $target !== '' && Redirects::wouldLoop($path, $target, $locale, $current?->getKey())) {
                $fail(__('translation.redirects.loop'));
            }
        };
    }

    /** A path of this website ("/new-page") or a full http(s) address */
    private function targetRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $target = Redirects::normalizeTarget(is_string($value) ? $value : '');

            $valid = Redirects::isInternal($target)
                ? ! preg_match('/[\s<>"]/', $target)
                : (filter_var($target, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $target));

            if (! $valid) {
                $fail(__('translation.redirects.target_invalid'));
            }
        };
    }
}
