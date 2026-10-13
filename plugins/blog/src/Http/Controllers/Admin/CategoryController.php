<?php

namespace Plugins\Blog\Http\Controllers\Admin;

use App\Helpers\SearchHelper;
use App\Support\EditLocale;
use App\Support\Translations;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Plugins\Blog\Models\BlogCategory;

#[Middleware('permission:show-blog-category', only: ['index'])]
#[Middleware('permission:create-blog-category', only: ['create', 'store'])]
#[Middleware('permission:edit-blog-category', only: ['edit', 'update'])]
#[Middleware('permission:delete-blog-category', only: ['destroy'])]
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = BlogCategory::withCount('posts')
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('blog-admin::categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('blog-admin::categories.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = BlogCategory::create($validated);

        return redirect()
            ->route('backend.blog.categories.edit', $category)
            ->with('success', __('blog::blog.category_created'));
    }

    public function edit(BlogCategory $category): View
    {
        return view('blog-admin::categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, BlogCategory $category)
    {
        // Translation instead of original (Multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            Translations::saveFromRequest($request, $category, $locale, [
                'name' => 'nullable|string|max:255',
                'slug' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ], 'name');

            return redirect()
                ->route('backend.blog.categories.edit', ['category' => $category, 'lang' => $locale])
                ->with('success', __('blog::blog.category_updated'));
        }

        $validated = $this->validateCategory($request, $category->id);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return redirect()
            ->route('backend.blog.categories.edit', $category)
            ->with('success', __('blog::blog.category_updated'));
    }

    public function destroy(BlogCategory $category)
    {
        $category->delete();

        return redirect()
            ->route('backend.blog.categories.index')
            ->with('success', __('blog::blog.category_deleted'));
    }

    protected function validateCategory(Request $request, $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blog_categories')->ignore($id),
            ],
            'description' => 'nullable|string',
        ]);
    }
}
