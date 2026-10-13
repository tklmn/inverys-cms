<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View as ViewContract;

/**
 * Layout of the profile pages (/profile, /backend/profile).
 *
 * Users with backend access see the profile in the backend — also under
 * /profile, where forms redirect after saving. Everyone else gets the
 * account area (backend.layouts.account) — not the website theme, which
 * does not know the UI components.
 */
class DynamicLayout extends Component
{
    public function render(): ViewContract
    {
        $hasBackendAccess = auth()->check() && auth()->user()->hasPermission('access-dashboard');

        return view($hasBackendAccess ? 'backend.layouts.app' : 'backend.layouts.account');
    }
}
