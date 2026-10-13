<?php

namespace Plugins\Blog\Http\Controllers;

use App\Support\Locales;
use App\Support\Seo\Descriptions;
use App\Support\Seo\SeoContext;
use App\Support\Seo\Urls;
use Illuminate\Http\RedirectResponse;
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

        // A page number beyond the last page does not exist
        abort_if($posts->currentPage() > max(1, $posts->lastPage()), 404);

        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        app(SeoContext::class)->fill([
            'title' => __('blog::blog.title'),
            'description' => Descriptions::pick(),
            'schemaType' => 'CollectionPage',
            'page' => $posts->currentPage(),
            // Date filters only repeat posts of the overview: not indexed, links followed
            'robots' => $request->filled('year') || $request->filled('month') ? 'noindex, follow' : null,
        ]);

        return view('blog::index', array_merge([
            'posts' => $posts,
            'categories' => $categories,
        ], $settings));
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        // Whoever may edit posts also sees drafts and scheduled
        // posts (preview) — everyone else only published ones
        $canPreview = cms()->permissions()->userHasPermission('edit-post');
        $query = ($canPreview ? Post::query() : Post::published())->with(['author', 'categories']);

        // Also via the translated slug of the current language
        $post = Post::findByTranslatedSlug($query, $slug);
        abort_if(! $post, 404);

        // One address per post and language: the slug of the current language
        if ((string) $post->slug !== $slug) {
            $queryString = $request->getQueryString();

            return redirect()->to(route('blog.show', $post->slug).($queryString ? '?'.$queryString : ''), 301);
        }

        $isPreview = ! $post->isPublished();
        $this->describePost($post, $isPreview);

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

    public function category(Request $request, BlogCategory $category): View|RedirectResponse
    {
        // One address per category and language: the slug of the current language
        $requested = (string) $request->route()?->originalParameter('category');
        if ($requested !== '' && (string) $category->slug !== $requested) {
            $queryString = $request->getQueryString();

            return redirect()->to(route('blog.category', $category->slug).($queryString ? '?'.$queryString : ''), 301);
        }

        $perPage = (int) $this->blogSetting('posts_per_page', '10');
        $settings = $this->blogSettings();

        $query = $category->posts()
            ->published()
            ->with(['author', 'categories']);

        $posts = $this->applySorting($query, $settings)
            ->paginate($perPage);

        abort_if($posts->currentPage() > max(1, $posts->lastPage()), 404);

        $categories = BlogCategory::whereHas('posts', fn ($q) => $q->published())
            ->withCount(['posts' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        app(SeoContext::class)->fill([
            'title' => __('blog::blog.posts_in_category', ['category' => $category->name]),
            'description' => Descriptions::pick($category->description),
            'schemaType' => 'CollectionPage',
            'page' => $posts->currentPage(),
            'breadcrumbs' => [['name' => __('blog::blog.title'), 'url' => route('blog.index')]],
        ]);

        return view('blog::index', array_merge([
            'posts' => $posts,
            'categories' => $categories,
            'currentCategory' => $category,
        ], $settings));
    }

    /** What search engines and social networks get to know about a post (SEO head hook) */
    protected function describePost(Post $post, bool $isPreview): void
    {
        $image = $post->featured_image ? 'storage/'.ltrim((string) $post->featured_image, '/') : null;

        app(SeoContext::class)->fill([
            'title' => (string) ($post->meta_title ?: $post->title),
            'description' => Descriptions::pick($post->meta_description, $post->excerpt, $post->content),
            'socialTitle' => (string) $post->title,
            'robots' => $isPreview ? 'noindex, nofollow' : null,
            'author' => $post->author?->full_name ?: null,
            'type' => SeoContext::TYPE_ARTICLE,
            'schemaType' => 'BlogPosting',
            'image' => Urls::firstSocialImage($image),
            'article' => array_filter([
                'published_time' => $post->published_at,
                'modified_time' => $post->updated_at,
                'author' => $post->author?->full_name,
                'section' => $post->categories->first()?->name,
            ]),
            'breadcrumbs' => [['name' => __('blog::blog.title'), 'url' => route('blog.index')]],
        ]);
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
