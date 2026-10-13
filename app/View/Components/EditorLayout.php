<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Full-screen layout of the page editor: without the backend's navigation
 * and header, so preview, structure and settings get the space.
 */
class EditorLayout extends Component
{
    public function render(): View
    {
        return view('backend.layouts.editor');
    }
}
