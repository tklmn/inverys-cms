<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class FrontendGuestLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        if (view()->exists('theme::layouts.guest')) {
            return view('theme::layouts.guest');
        }

        return view('frontend.layouts.guest');
    }
}
