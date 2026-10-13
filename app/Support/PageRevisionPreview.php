<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Page;

/** Preview of a page revision: the page in its old state, as visitors would see it */
final class PageRevisionPreview
{
    public static function render(Page $snapshot): string
    {
        $locales = app(Locales::class);
        $locales->setVisitor($locales->base());
        $locales->setCurrent($snapshot);
        app()->setLocale($locales->base());

        $snapshot->load(['children', 'parent']);
        $view = view()->exists('theme::page') ? 'theme::page' : 'frontend.page';

        return view($view, [
            'page' => $snapshot,
            'rootPage' => Page::where('is_root', true)->first(),
        ])->render();
    }
}
