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
use Plugins\Blog\Models\Post;

#[Middleware('permission:show-post', only: ['index'])]
#[Middleware('permission:create-post', only: ['create', 'store'])]
#[Middleware('permission:edit-post', only: ['edit', 'update'])]
#[Middleware('permission:delete-post', only: ['destroy'])]
class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::with(['author', 'categories'])
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'published') {
                    $query->where('is_active', true)->whereNotNull('published_at')->where('published_at', '<=', now());
                } elseif ($status === 'draft') {
                    $query->where(function ($q) {
                        $q->where('is_active', false)->orWhereNull('published_at');
                    });
                } elseif ($status === 'scheduled') {
                    $query->where('is_active', true)->where('published_at', '>', now());
                }
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('blog-admin::index', [
            'posts' => $posts,
        ]);
    }

    public function create(): View
    {
        $categories = BlogCategory::orderBy('name')->get();

        return view('blog-admin::create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);
        $validated = $this->applyStatus($validated);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $categories = $validated['categories'] ?? [];
        unset($validated['categories']);
        $post = Post::create($validated);
        $post->categories()->sync($categories);

        return redirect()
            ->route('backend.blog.edit', $post)
            ->with('success', __('blog::blog.created_successfully'));
    }

    public function edit(Post $post): View
    {
        $categories = BlogCategory::orderBy('name')->get();
        $selectedCategories = $post->categories()->pluck('blog_category_id')->toArray();

        return view('blog-admin::edit', [
            'post' => $post,
            'categories' => $categories,
            'selectedCategories' => $selectedCategories,
        ]);
    }

    public function update(Request $request, Post $post)
    {
        // Translation instead of original (Multilingual plugin)
        if ($locale = EditLocale::current($request)) {
            // A translation of a live post goes live as well: same approval rule
            Post::authorizeStatus($post->is_active, $post->published_at);

            Translations::saveFromRequest($request, $post, $locale, [
                'title' => 'nullable|string|max:255',
                'slug' => 'nullable|string|max:255',
                'excerpt' => 'nullable|string',
                'content' => 'nullable|string',
                'meta_title' => 'nullable|string|max:255',
                'meta_description' => 'nullable|string',
            ]);

            return redirect()
                ->route('backend.blog.edit', ['post' => $post, 'lang' => $locale])
                ->with('success', __('blog::blog.updated_successfully'));
        }

        $validated = $this->validatePost($request, $post->id);
        $validated = $this->applyStatus($validated);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $categories = $validated['categories'] ?? [];
        unset($validated['categories']);
        $post->update($validated);
        $post->categories()->sync($categories);

        return redirect()
            ->route('backend.blog.edit', $post)
            ->with('success', __('blog::blog.updated_successfully'));
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()
            ->route('backend.blog.index')
            ->with('success', __('blog::blog.deleted_successfully'));
    }

    protected function validatePost(Request $request, $id = null): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('posts')->ignore($id),
            ],
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:users,id',
            'post_status' => 'required|in:published,draft,scheduled',
            'published_at' => $request->input('post_status') === 'scheduled'
                ? 'required|date|after:now'
                : 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            // Unknown IDs are an input error, not a database error (500)
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:blog_categories,id',
        ]);
    }

    /**
     * Convert post_status dropdown into is_active + published_at fields.
     * Checks the matching permission for the chosen status.
     */
    protected function applyStatus(array $data): array
    {
        $status = $data['post_status'] ?? 'draft';
        unset($data['post_status']);

        $permissionMap = [
            'published' => 'publish-post',
            'scheduled' => 'schedule-post',
            'draft' => 'draft-post',
        ];

        $requiredPermission = $permissionMap[$status] ?? null;
        if ($requiredPermission && ! cms()->permissions()->userHasPermission($requiredPermission)) {
            abort(403, __('blog::blog.no_permission_status'));
        }

        match ($status) {
            'published' => $data = array_merge($data, [
                'is_active' => true,
                'published_at' => $data['published_at'] ?: now(),
            ]),
            'scheduled' => $data = array_merge($data, [
                'is_active' => true,
                // published_at comes from the form
            ]),
            'draft' => $data = array_merge($data, [
                'is_active' => false,
                'published_at' => null,
            ]),
        };

        return $data;
    }
}
