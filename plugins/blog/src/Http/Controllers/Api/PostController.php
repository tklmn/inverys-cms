<?php

namespace Plugins\Blog\Http\Controllers\Api;

use App\Helpers\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Plugins\Blog\Http\Resources\PostResource;
use Plugins\Blog\Models\Post;

class PostController extends Controller
{
    /**
     * List published posts (public) or all posts (authenticated with permission).
     */
    public function index(Request $request)
    {
        $query = Post::published()->with(['author', 'categories']);

        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->category)
                    ->orWhere('blog_categories.id', $request->category);
            });
        }

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($request->filled('featured') && $request->featured) {
            $query->whereNotNull('featured_image');
        }

        $sortField = in_array($request->sort, ['title', 'published_at', 'created_at'])
            ? $request->sort : 'published_at';
        $sortDir = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        return PostResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single published post by ID or slug.
     */
    public function show(string $id)
    {
        $post = is_numeric($id)
            ? Post::published()->with(['author', 'categories'])->findOrFail($id)
            : Post::published()->with(['author', 'categories'])->where('slug', $id)->firstOrFail();

        return new PostResource($post);
    }

    /**
     * Create a new post (authenticated).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts',
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:html,blocks',
            'blocks' => 'nullable|array',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:blog_categories,id',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        $data['author_id'] = $request->user()->id;

        $this->authorizeStatus($data);

        $post = Post::create($data);

        if (! empty($categoryIds)) {
            $post->categories()->sync($categoryIds);
        }

        $post->load(['author', 'categories']);

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    /**
     * Update an existing post (authenticated).
     */
    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('posts')->ignore($post->id)],
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:html,blocks',
            'blocks' => 'nullable|array',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:blog_categories,id',
        ]);

        if (array_key_exists('is_active', $data) || array_key_exists('published_at', $data)) {
            $this->authorizeStatus([
                'is_active' => $data['is_active'] ?? $post->is_active,
                'published_at' => array_key_exists('published_at', $data) ? $data['published_at'] : $post->published_at,
            ]);
        }

        if (isset($data['categories'])) {
            $post->categories()->sync($data['categories']);
            unset($data['categories']);
        }

        $post->update($data);
        $post->load(['author', 'categories']);

        return new PostResource($post);
    }

    /**
     * As in the backend (Admin\PostController::applyStatus): publishing
     * needs publish-post, scheduling schedule-post — otherwise the API would
     * bypass approval with create-post/edit-post alone.
     */
    protected function authorizeStatus(array $data): void
    {
        if (empty($data['is_active'])) {
            return;
        }

        $scheduled = ! empty($data['published_at']) && Carbon::parse($data['published_at'])->isFuture();
        $permission = $scheduled ? 'schedule-post' : 'publish-post';

        abort_unless(cms()->permissions()->userHasPermission($permission), 403, __('blog::blog.no_permission_status'));
    }

    /**
     * Soft-delete a post (authenticated).
     */
    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json(['message' => 'Post deleted.']);
    }
}
