<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Locales;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(?string $slug = null): View
    {
        $query = Page::with(['children', 'parent'])->where('is_active', true);

        // By the slug of the current content language, falling back to the original
        $page = $slug
            ? Page::findByTranslatedSlug($query, $slug)
            : $query->where('is_root', true)->first();

        abort_if(! $page, 404);

        if (! $slug) {
            app(Locales::class)->setCurrent($page);
        }

        $rootPage = Page::where('is_root', true)->first();

        $view = view()->exists('theme::page') ? 'theme::page' : 'frontend.page';

        return view($view, [
            'page' => $page,
            'rootPage' => $rootPage,
        ]);
    }
}
