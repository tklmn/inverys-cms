<?php

namespace App\View\Components;

use App\Services\ThemeManager;
use App\Support\ThemeViews;
use Illuminate\View\Component;
use Illuminate\View\View as ViewContract;

class FrontendAppLayout extends Component
{
    public function render(): ViewContract
    {
        // The visitor's own theme; ApplyVisitorTheme usually did this already
        app(ThemeViews::class)->useTheme(app(ThemeManager::class)->getActiveForUser());

        if (view()->exists('theme::layouts.app')) {
            return view('theme::layouts.app');
        }

        return view('frontend.layouts.app');
    }
}
