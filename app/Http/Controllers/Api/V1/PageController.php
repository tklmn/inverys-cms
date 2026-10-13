<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PageCreated;
use App\Events\PageDeleted;
use App\Events\PageUpdated;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\BlockValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::query();

        if ($request->has('search')) {
            $search = SearchHelper::escapeLike($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $pages = $query->orderBy('order')->paginate($perPage);

        return PageResource::collection($pages);
    }

    public function show(Page $page)
    {
        return new PageResource($page);
    }

    public function store(Request $request, BlockValidator $validator): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages',
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:html,blocks',
            'blocks' => 'nullable|array',
            'parent_id' => 'nullable|exists:pages,id',
            'is_active' => 'boolean',
            'is_root' => 'boolean',
            'template' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug(Str::slug($data['title']));
        }

        if (isset($data['blocks'])) {
            if (! $validator->validate($data['blocks'])) {
                return response()->json(['message' => 'Invalid blocks.', 'errors' => $validator->errors()], 422);
            }
        }

        // Pages consist of blocks; HTML without blocks becomes a text block
        // (Page::normalizeContent)
        $data['content_type'] ??= isset($data['blocks']) ? 'blocks' : 'html';

        $data['order'] = Page::where('parent_id', $data['parent_id'] ?? null)->max('order') + 1;
        $page = Page::create($data);

        PageCreated::dispatch($page);

        return (new PageResource($page))->response()->setStatusCode(201);
    }

    public function update(Request $request, Page $page, BlockValidator $validator)
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('pages')->ignore($page->id)],
            'content' => 'nullable|string',
            'content_type' => 'sometimes|in:html,blocks',
            'blocks' => 'nullable|array',
            'parent_id' => 'nullable|exists:pages,id',
            'is_active' => 'boolean',
            'is_root' => 'boolean',
            'template' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
        ]);

        if (isset($data['blocks'])) {
            if (! $validator->validate($data['blocks'])) {
                return response()->json(['message' => 'Invalid blocks.', 'errors' => $validator->errors()], 422);
            }
        }

        // New HTML without blocks replaces the content: it becomes a text block.
        // Without this step the search text of the old blocks would overwrite the HTML.
        if (array_key_exists('content', $data) && ! isset($data['blocks'])) {
            $data['content_type'] = 'html';
        }

        // Prevent moving a page under itself or one of its own descendants (would create a cycle).
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $newParent = (int) $data['parent_id'];
            if ($newParent === $page->id || in_array($newParent, $this->descendantIds($page->id), true)) {
                return response()->json(['message' => 'A page cannot be moved under itself or its own descendant.'], 422);
            }
        }

        $page->update($data);

        PageUpdated::dispatch($page);

        return new PageResource($page);
    }

    public function destroy(Page $page): JsonResponse
    {
        if ($page->is_root) {
            return response()->json(['message' => 'The homepage cannot be deleted.'], 422);
        }

        PageDeleted::dispatch($page);
        $page->delete();

        return response()->json(['message' => 'Page deleted.']);
    }

    /**
     * Generate a slug that is unique across all pages (including trashed).
     */
    private function uniqueSlug(string $base): string
    {
        $base = $base ?: 'page';
        $slug = $base;
        $counter = 1;

        while (Page::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }

    /**
     * Collect all descendant page IDs of the given page via a single parent map.
     */
    private function descendantIds(int $pageId): array
    {
        $parentMap = Page::pluck('parent_id', 'id')->all();
        $ids = [];
        $queue = [$pageId];

        while ($queue) {
            $current = (int) array_shift($queue);
            foreach ($parentMap as $id => $parentId) {
                if ((int) $parentId === $current) {
                    $ids[] = (int) $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }
}
