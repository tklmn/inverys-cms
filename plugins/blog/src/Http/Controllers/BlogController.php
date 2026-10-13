<?php

namespace Plugins\Blog\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\Blog\Models\BlogCategory;
use Plugins\Blog\Models\Post;

class BlogController extends Controller
{
    protected function blogSetting(string $key, string $default = ''): string
    {
        return cms()->settings()->get("blog.{$key}", $default);
    }

    protected function blogSettings(): array
    {
        $boolKeys = [
            'show_pagination', 'show_author', 'show_date', 'show_categories',
            'show_excerpt', 'show_featured_image', 'show_read_more',
            'show_breadcrumbs', 'show_prev_next', 'show_sidebar', 'show_rss_link',
        ];

        $raw = cms()->settings()->getByPrefix('blog.');

        $settings = [];
        foreach ($boolKeys as $key) {
            $settings[$key] = ($raw[$key] ?? '1') === '1';
        }

        $settings['columns'] = (int) ($raw['columns'] ?? '1');
        $settings['sort_by'] = $raw['sort_by'] ?? 'published_at';
        $settings['sort_direction'] = $raw['sort_direction'] ?? 'desc';
        $settings['read_more_text'] = $raw['read_more_text'] ?? '';

        return $settings;
    }

    protected function applySorting($query, array $settings)
    {
        $sortBy = in_array($settings['sort_by'], ['published_at', 'title', 'created_at']) ? $settings['sort_by'] : 'published_at';
        $direction = $settings['sort_direction'] === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $direction);
    }

    public function index(Request $request): View
    {
        $perPage = (int) $this->blogSetting('posts_per_page', '10');
        $settings = $this->blogSettings();

        $query = Post::published()
            ->with(['author', 'categories'])
            ->when($request->year, fn ($q, $year) => $q->whereYear('published_at', $year))
            ->when($request->month, fn ($q, $month) => $q->whereMonth('published_at', $month));

        $posts = $this->applySorting($query, $settings)
            ->paginate($perPage)
            ->withQueryString();

        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        return view('blog::index', array_merge([
            'posts' => $posts,
            'categories' => $categories,
        ], $settings));
    }

    public function show(string $slug): Response
    {
        // Whoever may edit posts also sees drafts and scheduled
        // posts (preview) — everyone else only published ones
        $canPreview = cms()->permissions()->userHasPermission('edit-post');
        $query = ($canPreview ? Post::query() : Post::published())->with(['author', 'categories']);

        // Also via the translated slug of the current language
        $post = Post::findByTranslatedSlug($query, $slug);
        abort_if(! $post, 404);

        $isPreview = ! $post->isPublished();

        // Previous/next only to published posts — in the preview too
        $prev = $next = null;
        if ($post->published_at) {
            $prev = Post::published()
                ->where('published_at', '<', $post->published_at)
                ->orderByDesc('published_at')
                ->first();

            $next = Post::published()
                ->where('published_at', '>', $post->published_at)
                ->orderBy('published_at')
                ->first();
        }

        $response = response()->view('blog::show', array_merge([
            'post' => $post,
            'prev' => $prev,
            'next' => $next,
            'isPreview' => $isPreview,
        ], $this->blogSettings()));

        // Never cache the preview (browser, proxy) and do not index it
        if ($isPreview) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /**
     * View a revision: the post in its old state, as visitors would see it
     * (App\Support\Revisionables, option "preview"). Nothing is saved.
     */
    public function revisionPreview(Post $snapshot): string
    {
        $locales = app(Locales::class);
        $locales->setVisitor($locales->base());
        $locales->setCurrent($snapshot);
        app()->setLocale($locales->base());

        return view('blog::show', array_merge([
            'post' => $snapshot->load(['author', 'categories']),
            'prev' => null,
            'next' => null,
            // No draft notice: the core sets the revision notice, the header sets noindex
            'isPreview' => false,
        ], $this->blogSettings()))->render();
    }

    public function category(BlogCategory $category): View
    {
        $perPage = (int) $this->blogSetting('posts_per_page', '10');
        $settings = $this->blogSettings();

        $query = $category->posts()
            ->published()
            ->with(['author', 'categories']);

        $posts = $this->applySorting($query, $settings)
            ->paginate($perPage);

        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        return view('blog::index', array_merge([
            'posts' => $posts,
            'categories' => $categories,
            'currentCategory' => $category,
        ], $settings));
    }

    public function feed()
    {
        $posts = Post::published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('blog::feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
