<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageCategory;
use App\Support\EditLocale;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;

#[Middleware('permission:show-page-category', only: ['index', 'show'])]
#[Middleware('permission:create-page-category', only: ['create', 'store'])]
#[Middleware('permission:edit-page-category', only: ['edit', 'update'])]
#[Middleware('permission:delete-page-category', only: ['destroy', 'restore'])]
class PageCategoryController extends Controller
{
    public function index(): View
    {
        $categories = PageCategory::withCount('pages')->orderBy('name')->paginate(config('cms.pagination.pages', 15));

        return view('backend.page_categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('backend.page_categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $data['slug'] = Str::slug($data['name']);
        PageCategory::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('backend.page-categories.index')
            ->with('success', __('translation.pages.category_created'));
    }

    public function edit(PageCategory $pageCategory): View
    {
        return view('backend.page_categories.edit', compact('pageCategory'));
    }

    public function update(Request $request, PageCategory $pageCategory): RedirectResponse
    {
        // Translation: name and description of the language, the original stays
        if ($locale = EditLocale::current($request)) {
            Translations::saveFromRequest($request, $pageCategory, $locale, [
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ], null);

            return redirect()->route('backend.page-categories.edit', ['page_category' => $pageCategory, 'lang' => $locale])
                ->with('success', __('translation.pages.category_updated'));
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $data['slug'] = Str::slug($data['name']);
        $pageCategory->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('backend.page-categories.edit', $pageCategory)
            ->with('success', __('translation.pages.category_updated'));
    }

    public function destroy(PageCategory $pageCategory): RedirectResponse
    {
        $pageCategory->delete();

        return redirect()->route('backend.page-categories.index')
            ->with('success', __('translation.pages.category_deleted'));
    }
}
