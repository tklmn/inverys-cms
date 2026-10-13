<?php

namespace App\Http\Controllers\Admin;

use App\Events\PageCreated;
use App\Events\PageDeleted;
use App\Events\PageUpdated;
use App\Helpers\HtmlHelper;
use App\Helpers\PageRouteHelper;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Middleware\InjectPluginHooks;
use App\Models\Page;
use App\Models\PageCategory;
use App\Services\BlockRenderer;
use App\Services\BlockValidator;
use App\Support\EditLocale;
use App\Support\EditorCanvas;
use App\Support\Locales;
use App\Support\PageStarters;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

#[Middleware('permission:show-page', only: ['index', 'show', 'tree'])]
#[Middleware('permission:create-page', only: ['create', 'store'])]
#[Middleware('permission:edit-page', only: ['edit', 'update', 'updateTree', 'saveBlocks', 'previewBlocks', 'canvas'])]
#[Middleware('permission:delete-page', only: ['destroy', 'restore'])]
class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        // Paginate only root pages, eager-load childrenRecursive for hierarchy
        $pages = Page::with(['childrenRecursive', 'children'])
            ->withCount('children')
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($status === 'trashed') {
                    $query->onlyTrashed();
                }
            })
            ->when($request->menu, function ($query, $menu) {
                if ($menu === 'visible') {
                    $query->where('hide_in_menu', false);
                } elseif ($menu === 'hidden') {
                    $query->where('hide_in_menu', true);
                }
            })
            // Restrict to root pages only for the hierarchical view. When a text search is
            // active we return a flat list so matching child pages are also found.
            ->when(! $request->filled('search'), fn ($query) => $query->whereNull('parent_id'))
            ->orderBy('order')
            ->paginate(config('cms.pagination.pages'))
            ->withQueryString();

        // "New page" dialog directly in the list
        $canCreate = $request->user()?->hasPermission('create-page') ?? false;

        return view('backend.pages.index', [
            'pages' => $pages,
            'parentOptions' => $canCreate ? $this->buildPageOptions(Page::select('id', 'title', 'level', 'order', 'parent_id')->orderBy('level')->orderBy('order')->get()) : [],
            'starters' => $canCreate ? PageStarters::all() : [],
        ]);
    }

    /**
     * Display a tree view of pages
     */
    public function tree(): View
    {
        $rootPages = Page::where('parent_id', null)
            ->orderBy('order')
            ->with('childrenRecursive')
            ->get();

        return view('backend.pages.tree', [
            'rootPages' => $rootPages,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $pages = Page::select('id', 'title', 'level', 'order', 'parent_id')
            ->orderBy('level')
            ->orderBy('order')
            ->get();

        return view('backend.pages.create', [
            'parentOptions' => $this->buildPageOptions($pages),
            'starters' => PageStarters::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validatedData = $this->validatePage($request);
        $starter = $request->validate([
            'starter' => ['nullable', Rule::in(array_keys(PageStarters::all()))],
        ])['starter'] ?? 'blank';

        $isRoot = ! empty($validatedData['is_root']);

        // Homepage gets slug "/", others get auto-generated slug if empty
        if ($isRoot) {
            $validatedData['slug'] = '/';
        } elseif (empty($validatedData['slug'])) {
            $validatedData['slug'] = Str::slug($validatedData['title']);
        }

        $maxOrder = Page::where('parent_id', $request->parent_id)->max('order') ?? -1;
        $validatedData['order'] = $maxOrder + 1;

        // New pages start as a draft — they only go online once they are finished
        $validatedData['is_active'] ??= false;

        // Blocks from the request, otherwise the chosen starter template
        $validatedData['content_type'] = 'blocks';
        $validatedData['blocks'] = $this->blocksFromRequest($request) ?? PageStarters::blocks($starter, $validatedData['title']);

        $categories = $request->input('categories', []);

        $page = DB::transaction(function () use ($validatedData, $categories) {
            $page = Page::create($validatedData);
            $page->categories()->sync($categories);

            return $page;
        });

        PageCreated::dispatch($page);

        return redirect()
            ->route('backend.pages.edit', $page)
            ->with('success', __('translation.pages.created_successfully'));
    }

    /**
     * Valid blocks from the editor's hidden field, otherwise null.
     *
     * @return list<array<string, mixed>>|null
     */
    protected function blocksFromRequest(Request $request): ?array
    {
        if (! $request->filled('blocks')) {
            return null;
        }

        $blocks = json_decode((string) $request->input('blocks'), true);

        return is_array($blocks) && app(BlockValidator::class)->validate($blocks) ? $blocks : null;
    }

    /**
     * Display the specified resource.
     */
    public function show(Page $page): RedirectResponse
    {
        return redirect()->route('backend.pages.edit', $page);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Page $page): View
    {
        $pages = Page::select('id', 'title', 'level', 'order', 'parent_id')
            ->where('id', '!=', $page->id)
            ->orderBy('level')
            ->orderBy('order')
            ->get();
        $categories = PageCategory::orderBy('name')->get();
        $selectedCategories = $page->categories()->pluck('page_category_id')->toArray();

        return view('backend.pages.edit', [
            'page' => $page,
            'pages' => $pages,
            'parentOptions' => $this->buildPageOptions($pages, $page->parent_id),
            'categories' => $categories,
            'selectedCategories' => $selectedCategories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Page $page): RedirectResponse
    {
        // Translation edited instead of the original (multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            return $this->updateTranslation($request, $page, $locale);
        }

        $validatedData = $this->validatePage($request, $page->id);
        // Prevent parent loop
        $parent = $request->parent_id ? Page::findOrFail($request->parent_id) : null;
        if ($parent && $this->isDescendant($parent, $page)) {
            return back()
                ->withInput()
                ->withErrors(['parent_id' => __('translation.pages.parent_loop_error')]);
        }

        $isRoot = ! empty($validatedData['is_root']);

        // Homepage gets slug "/", others need a real slug
        if ($isRoot) {
            $validatedData['slug'] = '/';
        } elseif (empty($validatedData['slug'])) {
            $validatedData['slug'] = Str::slug($validatedData['title']);
        }

        // One editor: pages always consist of blocks
        $validatedData['content_type'] = 'blocks';
        unset($validatedData['content']);
        if (($blocks = $this->blocksFromRequest($request)) !== null) {
            $validatedData['blocks'] = $blocks;
        }

        $categories = $request->input('categories', []);

        DB::transaction(function () use ($page, $validatedData, $categories) {
            $page->update($validatedData);
            $page->categories()->sync($categories);
        });

        PageUpdated::dispatch($page);

        return redirect()
            ->route('backend.pages.edit', $page)
            ->with('success', __('translation.pages.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Page $page): RedirectResponse
    {
        if ($page->is_root) {
            return redirect()
                ->route('backend.pages.index')
                ->withErrors(['page' => __('translation.pages.cannot_delete_homepage')]);
        }

        PageDeleted::dispatch($page);
        $page->delete();

        return redirect()
            ->route('backend.pages.index')
            ->with('success', __('translation.pages.deleted_successfully'));
    }

    /**
     * Restore a soft-deleted page.
     */
    public function restore($id): RedirectResponse
    {
        $page = Page::withTrashed()->findOrFail($id);
        $page->restore();

        return redirect()
            ->route('backend.pages.index')
            ->with('success', __('translation.pages.restored_successfully'));
    }

    /**
     * Update page tree structure via AJAX.
     */
    public function updateTree(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tree' => 'required|array',
            'tree.*.id' => 'required|exists:pages,id',
            'tree.*.parent_id' => 'nullable|exists:pages,id',
            'tree.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            foreach ($request->tree as $node) {
                $page = Page::find($node['id']);
                if (! $page) {
                    continue;
                }

                $parentId = $node['parent_id'] ?? null;

                // Prevent self references
                if ((int) $parentId === $page->id) {
                    continue;
                }

                // Prevent circular references across the whole subtree, not just direct children
                if ($parentId && in_array((int) $parentId, $this->getDescendantIds($page->id), true)) {
                    continue;
                }

                if ((int) $parentId !== (int) $page->parent_id) {
                    // Parent page has changed
                    $page->moveTo($parentId, $node['order']);
                } elseif ((int) $node['order'] !== (int) $page->order) {
                    // Only the order has changed
                    $page->update(['order' => $node['order']]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('translation.pages.tree_updated_successfully'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'success' => false,
                'message' => __('translation.pages.tree_update_failed'),
            ], 500);
        }
    }

    /**
     * Save blocks via AJAX (auto-save on drag/drop).
     */
    public function saveBlocks(Request $request, Page $page): JsonResponse
    {
        $blocks = json_decode((string) $request->input('blocks', '[]'), true);
        $blockValidator = app(BlockValidator::class);

        if (is_array($blocks) && $blockValidator->validate($blocks)) {
            // In translation mode the builder saves the language's blocks.
            // An auto-save is not "fully translated" yet — only a real save is
            if ($locale = EditLocale::current($request)) {
                $page->saveTranslations($locale, ['blocks' => $blocks], reviewed: false);

                return response()->json(['success' => true]);
            }

            $page->update(['blocks' => $blocks]);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid blocks'], 422);
    }

    /**
     * Save the translation of a page.
     *
     * Only the translatable fields; everything else applies to all languages and
     * is edited in the original. Empty fields remove the translation —
     * then the original applies again.
     */
    protected function updateTranslation(Request $request, Page $page, string $locale): RedirectResponse
    {
        $rules = [
            'title' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'nav_title' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            'canonical_url' => self::canonicalRule(),
            'aria_label' => 'nullable|string|max:255',
        ];

        // The homepage has no slug, in any language
        if (! $page->is_root) {
            $rules['slug'] = 'nullable|string|max:255';
        }

        Translations::saveFromRequest($request, $page, $locale, $rules, $page->is_root ? null : 'title');

        // Otherwise the editor saves the language's blocks itself (saveBlocks)
        if (($blocks = $this->blocksFromRequest($request)) !== null) {
            $page->saveTranslations($locale, ['blocks' => $blocks]);
        }

        return redirect()
            ->route('backend.pages.edit', ['page' => $page, 'lang' => $locale])
            ->with('success', __('translation.pages.updated_successfully'));
    }

    /**
     * Preview blocks as rendered HTML.
     */
    public function previewBlocks(Request $request): JsonResponse
    {
        $blocks = json_decode((string) $request->input('blocks', '[]'), true);
        if (! is_array($blocks) || ! app(BlockValidator::class)->validate($blocks)) {
            return response()->json(['message' => 'Invalid blocks'], 422);
        }
        $html = app(BlockRenderer::class)->render($blocks);

        return response()->json(['html' => $html]);
    }

    /**
     * Preview in the editor: the page as the website shows it — with theme,
     * header and footer, also as a draft and in the edited language.
     *
     * Runs in the editor's iframe. A small script captures clicks on links
     * and forms so the preview does not navigate away.
     */
    public function canvas(Request $request, Page $page): Response
    {
        $locales = app(Locales::class);
        $locale = EditLocale::current($request) ?? $locales->base();

        $locales->setVisitor($locale);
        $locales->setCurrent($page);
        app()->setLocale($locale);

        $view = view()->exists('theme::page') ? 'theme::page' : 'frontend.page';

        // Mark blocks and text passages for selection and inline editing
        EditorCanvas::enable();
        try {
            $html = view($view, [
                'page' => $page->load(['children', 'parent']),
                'rootPage' => Page::where('is_root', true)->first(),
            ])->render();
        } finally {
            EditorCanvas::enable(false);
        }

        $script = view('backend.pages.partials.canvas-script')->render();
        $html = str_contains($html, '</body>') ? InjectPluginHooks::insertBefore($html, '</body>', $script, true) : $html.$script;

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Canonical address: a full http(s) address or a path of this website
     * ("/other-page") — anything else would send search engines nowhere.
     */
    protected static function canonicalRule(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:#^(https?://[^\s<>"]+|/(?!/)[^\s<>"]*)$#i'];
    }

    /**
     * Validate page data.
     */
    protected function validatePage(Request $request, $id = null)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => array_filter([
                'nullable',
                'string',
                'max:255',
                $request->boolean('is_root') ? null : 'not_in:/',
                // Addresses the system itself uses (/backend, /login …) would never be reachable
                Rule::notIn(PageRouteHelper::reservedSlugs()),
                Rule::unique('pages')->ignore($id),
            ]),
            'content' => 'nullable|string',
            'parent_id' => 'nullable|exists:pages,id',
            'is_active' => 'boolean',
            'hide_in_menu' => 'boolean',
            'is_root' => 'boolean',
            'template' => 'nullable|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'nav_title' => 'nullable|string|max:255',
            // Full address (http/https) or path of this website ("/blog")
            'external_url' => ['nullable', 'string', 'max:255', 'regex:#^(https?://[^\s]+|/(?!/)[^\s]*)$#i'],
            'external_url_new_window' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_robots' => 'nullable|string|max:255',
            'meta_author' => 'nullable|string|max:255',
            'include_in_search' => 'boolean',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            // Image for social networks: path from the media library or full address
            'og_image' => ['nullable', 'string', 'max:255', 'regex:#^(https?://[^\s<>"]+|/?[^\s<>":]+)$#i'],
            'canonical_url' => self::canonicalRule(),
            'aria_label' => 'nullable|string|max:255',
            'header_image' => 'nullable|string|max:255',
            'extra_css' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:page_categories,id',
        ]);
        unset($validated['categories']);

        if (! empty($validated['extra_css'])) {
            $validated['extra_css'] = $this->sanitizeCss($validated['extra_css']);
        }

        return $validated;
    }

    /**
     * Build a dropdown array for the parent page selection.
     */
    protected function buildPageOptions($pages, $selectedId = null)
    {
        $options = ['' => __('translation.pages.no_parent')];

        foreach ($pages as $page) {
            $prefix = str_repeat('— ', $page->level);
            $options[$page->id] = $prefix.$page->title;
        }

        return $options;
    }

    /**
     * Check if a page is a descendant of another page.
     */
    protected function isDescendant($parent, $page)
    {
        if ($parent->id === $page->id) {
            return true;
        }

        // Load all descendant IDs in a single query instead of recursive lazy loading
        $descendantIds = $this->getDescendantIds($page->id);

        return in_array($parent->id, $descendantIds);
    }

    protected function getDescendantIds(int $pageId): array
    {
        // Load full parent_id map once and traverse in memory
        $parentMap = Page::pluck('parent_id', 'id')->all();
        $ids = [];
        $queue = [$pageId];

        while ($queue) {
            $current = array_shift($queue);
            foreach ($parentMap as $id => $parentId) {
                if ($parentId === $current) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Sanitize CSS by removing dangerous patterns.
     */
    private function sanitizeCss(string $css): string
    {
        return HtmlHelper::cleanCss($css);
    }
}
