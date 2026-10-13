<?php

namespace Plugins\Blog\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Plugins\Blog\Http\Resources\CategoryResource;
use Plugins\Blog\Models\BlogCategory;

class CategoryController extends Controller
{
    /**
     * List all categories with post counts.
     */
    public function index()
    {
        $categories = BlogCategory::withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Show a single category by ID or slug, with paginated posts.
     */
    public function show(Request $request, string $id)
    {
        $category = is_numeric($id)
            ? BlogCategory::withCount(['posts' => fn ($q) => $q->published()])->findOrFail($id)
            : BlogCategory::withCount(['posts' => fn ($q) => $q->published()])->where('slug', $id)->firstOrFail();

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $category->setRelation(
            'posts',
            $category->posts()->published()->with(['author', 'categories'])->paginate($perPage)
        );

        return new CategoryResource($category);
    }

    /**
     * Create a new category (authenticated).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_categories',
            'description' => 'nullable|string',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = BlogCategory::create($data);

        return (new CategoryResource($category->loadCount('posts')))->response()->setStatusCode(201);
    }

    /**
     * Update an existing category (authenticated).
     */
    public function update(Request $request, BlogCategory $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('blog_categories')->ignore($category->id)],
            'description' => 'nullable|string',
        ]);

        $category->update($data);

        return new CategoryResource($category->loadCount('posts'));
    }

    /**
     * Delete a category (authenticated).
     */
    public function destroy(BlogCategory $category)
    {
        $category->posts()->detach();
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
