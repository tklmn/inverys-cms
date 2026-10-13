<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Locales;
use App\Support\Seo\Descriptions;
use App\Support\Seo\SeoContext;
use App\Support\Seo\Urls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request, ?string $slug = null): View|RedirectResponse
    {
        $query = Page::with(['children', 'parent'])->where('is_active', true);

        // By the slug of the current content language, falling back to the original
        $page = $slug
            ? Page::findByTranslatedSlug(clone $query, $slug)
            : (clone $query)->where('is_root', true)->first();

        // The same address in other letter case (/About-Us) — leads to the stored one below
        if (! $page && $slug !== null) {
            $page = (clone $query)->whereRaw('LOWER(slug) = ?', [mb_strtolower($slug)])->first();
        }

        abort_if(! $page, 404);

        app(Locales::class)->setCurrent($page);

        // One address per page and language: requested via the original slug
        // or another letter case, the page answers under the slug of the
        // current language (/fr/about-us → /fr/a-propos)
        if ($slug !== null && ! $page->is_root && (string) $page->slug !== $slug) {
            $query = $request->getQueryString();

            return redirect()->to(route('page.show', $page->slug).($query ? '?'.$query : ''), 301);
        }

        $rootPage = Page::where('is_root', true)->first();

        $this->describe($page, $rootPage);

        $view = view()->exists('theme::page') ? 'theme::page' : 'frontend.page';

        return view($view, [
            'page' => $page,
            'rootPage' => $rootPage,
        ]);
    }

    /** What search engines and social networks get to know (SEO head hook) */
    private function describe(Page $page, ?Page $rootPage): void
    {
        $breadcrumbs = [];
        $parent = $page->parent;
        $seen = [$page->id => true];

        while ($parent && ! isset($seen[$parent->id])) {
            $seen[$parent->id] = true;
            if (! $rootPage || $parent->id !== $rootPage->id) {
                array_unshift($breadcrumbs, [
                    'name' => (string) ($parent->nav_title ?: $parent->title),
                    'url' => $parent->is_root ? route('home') : route('page.show', $parent->slug),
                ]);
            }
            $parent = $parent->parent;
        }

        app(SeoContext::class)->fill([
            'title' => (string) ($page->meta_title ?: $page->title),
            // Own description, otherwise the subtitle or the beginning of the text
            'description' => Descriptions::pick($page->meta_description, $page->subtitle, $page->content),
            'socialTitle' => $page->og_title ?: null,
            'socialDescription' => $page->og_description ? Descriptions::plain($page->og_description) : null,
            'canonical' => $page->canonical_url ?: null,
            'robots' => $page->meta_robots ?: null,
            'author' => $page->meta_author ?: null,
            'image' => Urls::firstSocialImage($page->og_image, $page->header_image),
            'schemaType' => 'WebPage',
            'breadcrumbs' => $breadcrumbs,
            'home' => (bool) $page->is_root,
        ]);
    }
}
