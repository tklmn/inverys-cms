@php
    $menuPages = \App\Models\Page::whereNull('parent_id')
        ->where('is_active', true)
        ->where('hide_in_menu', false)
        ->orderBy('order')
        ->with(['children' => function($q) {
            $q->where('is_active', true)->where('hide_in_menu', false)->orderBy('order');
        }])
        ->get();
@endphp
<nav id="modern-nav" class="modern-nav bg-white/80 dark:bg-gray-900/80 border-b border-gray-200/50 dark:border-gray-800 sticky top-0 z-50">
    <div class="{{ theme('max_width', 'max-w-5xl') }} mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="shrink-0 flex items-center">
                <a href="{{ url('/') }}" class="site-logo flex items-center">
                    @if(theme('logo_url'))
                        @include('frontend.partials.site-logo', ['logo' => theme('logo_url'), 'logoDark' => theme('logo_url_dark'), 'siteName' => setting('site_name', __('translation.app_name'))])
                    @else
                        <span class="text-xl font-bold modern-gradient-text">{{ setting('site_name', __('translation.app_name')) }}</span>
                    @endif
                </a>
            </div>

            <!-- Center Navigation (desktop): whatever does not fit moves under "More" (script below) -->
            <div class="hidden min-w-0 flex-1 lg:flex lg:items-center lg:justify-center lg:space-x-1 lg:px-4" data-modern-nav>
                @foreach($menuPages as $page)
                    @php
                        $isExternal = !empty($page->external_url);
                        $linkUrl = $isExternal
                            ? $page->external_url
                            : ($page->is_root ? route('home') : route('page.show', $page->slug));
                        $isLocalExternal = $isExternal && str_starts_with($page->external_url ?? '', '/');
                        $isActive = $isLocalExternal
                            ? (request()->is(ltrim($page->external_url, '/')) || request()->is(ltrim($page->external_url, '/') . '/*'))
                            : (!$isExternal && (
                                ($page->is_root && request()->is('/')) ||
                                request()->is($page->slug) ||
                                request()->is($page->slug . '/*') ||
                                (!$isExternal && $page->children->contains(fn($c) => request()->is($c->slug) || request()->is($c->slug . '/*')))
                            ));
                        $hasChildren = !$isExternal && $page->children->count() > 0;
                        $activeClass = $isActive
                            ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300'
                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800';
                    @endphp
                    @if($hasChildren)
                        <div class="relative modern-dropdown">
                            <button type="button"
                                    aria-expanded="false"
                                    aria-haspopup="true"
                                    class="inline-flex items-center whitespace-nowrap px-3 py-2 rounded-full text-sm font-medium transition-colors {{ $activeClass }}">
                                {{ $page->nav_title ?: $page->title }}
                                <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div class="modern-dropdown-menu absolute left-0 top-full mt-1 w-48 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50 py-1"
                                 style="display:none; opacity:0; transform:translateY(-4px); transition: opacity 150ms ease, transform 150ms ease;">
                                @foreach($page->children as $child)
                                    @php
                                        $childIsExternal = !empty($child->external_url);
                                        $childUrl = $childIsExternal
                                            ? $child->external_url
                                            : route('page.show', $child->slug);
                                        $childIsActive = !$childIsExternal && (request()->is($child->slug) || request()->is($child->slug . '/*'));
                                    @endphp
                                    <a href="{{ $childUrl }}"
                                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $childIsActive ? 'font-semibold text-indigo-600 dark:text-indigo-400' : '' }}"
                                       @if($childIsExternal && $child->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                                        {{ $child->nav_title ?: $child->title }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $linkUrl }}"
                           class="whitespace-nowrap px-3 py-2 rounded-full text-sm font-medium transition-colors {{ $activeClass }}"
                           @if($isExternal && $page->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $page->nav_title ?: $page->title }}
                        </a>
                    @endif
                @endforeach
                <div class="relative modern-dropdown" data-modern-more hidden>
                    <button type="button" aria-expanded="false" aria-haspopup="true"
                            class="inline-flex items-center whitespace-nowrap px-3 py-2 rounded-full text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        {{ __('translation.frontend.nav_more') }}
                        <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="modern-dropdown-menu modern-more-list absolute right-0 top-full mt-1 w-56 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50 p-1"
                         data-modern-more-list style="display:none; opacity:0; transform:translateY(-4px); transition: opacity 150ms ease, transform 150ms ease;"></div>
                </div>
            </div>

            <!-- Right Side (desktop) -->
            <div class="hidden shrink-0 lg:flex lg:items-center lg:space-x-1">
                <x-ui.theme-toggle button-class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" />
                {{-- Hook for plugins (e.g. language menu) --}}
                @foreach(app('plugin.navigation_hooks') as $navHook)
                    @includeIf($navHook)
                @endforeach

                @if(theme('show_search', true))
                <button type="button" data-csp="nav-search-toggle" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('translation.search') }}">
                    <i class="bi bi-search text-lg"></i>
                    <span class="sr-only">{{ __('translation.search') }}</span>
                </button>
                @endif
                @auth
                    @if(auth()->user()->hasPermission('access-dashboard'))
                    <a href="{{ route('dashboard') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('translation.navigation.dashboard') }}">
                        <i class="bi bi-speedometer2 text-lg"></i>
                        <span class="sr-only">{{ __('translation.navigation.dashboard') }}</span>
                    </a>
                    @endif
                    @if(theme('show_profile', true))
                    <a href="{{ route('profile.edit') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('translation.profile') }}">
                        <i class="bi bi-person text-lg"></i>
                        <span class="sr-only">{{ __('translation.profile') }}</span>
                    </a>
                    @endif
                    @if(theme('show_logout', true))
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" title="{{ __('translation.logout') }}">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                            <span class="sr-only">{{ __('translation.logout') }}</span>
                        </button>
                    </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="whitespace-nowrap px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                        {{ __('translation.login') }}
                    </a>
                    @if (Route::has('register') && registration_open())
                        <a href="{{ route('register') }}" class="whitespace-nowrap px-4 py-2 text-sm font-medium text-white rounded-full modern-gradient hover:opacity-90 transition-opacity">
                            {{ __('translation.register') }}
                        </a>
                    @endif
                @endauth
            </div>

            <!-- Mobile Menu Button -->
            <div class="flex items-center lg:hidden">
                <button id="modern-nav-toggle" class="nav-hamburger p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" aria-expanded="false" aria-controls="modern-nav-mobile-menu" aria-label="{{ __('translation.frontend.toggle_navigation') }}">
                    <span class="nav-hamburger-line"></span>
                    <span class="nav-hamburger-line"></span>
                    <span class="nav-hamburger-line"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="modern-nav-mobile-menu" class="nav-mobile-panel lg:hidden border-t border-gray-200 dark:border-gray-800" aria-hidden="true" inert>
        <div class="nav-mobile-panel-inner">
            <div class="px-4 py-3 space-y-1">
                @foreach($menuPages as $page)
                    @php
                        $mIsExternal = !empty($page->external_url);
                        $mLinkUrl = $mIsExternal
                            ? $page->external_url
                            : ($page->is_root ? route('home') : route('page.show', $page->slug));
                        $mHasChildren = !$mIsExternal && $page->children->count() > 0;
                    @endphp
                    @if($mHasChildren)
                        <button type="button" data-csp="modern-submenu-toggle" class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" aria-expanded="false">
                            {{ $page->nav_title ?: $page->title }}
                            <svg class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="nav-submenu-panel overflow-hidden" style="display:none">
                            <a href="{{ $mLinkUrl }}" class="block pl-7 pr-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                                {{ $page->nav_title ?: $page->title }}
                            </a>
                            @foreach($page->children as $child)
                                @php
                                    $mcIsExternal = !empty($child->external_url);
                                    $mcUrl = $mcIsExternal ? $child->external_url : route('page.show', $child->slug);
                                @endphp
                                <a href="{{ $mcUrl }}" class="block pl-7 pr-3 py-2 rounded-lg text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                                   @if($mcIsExternal && $child->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                                    {{ $child->nav_title ?: $child->title }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <a href="{{ $mLinkUrl }}" class="block px-3 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                           @if($mIsExternal && $page->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                            {{ $page->nav_title ?: $page->title }}
                        </a>
                    @endif
                @endforeach
            </div>
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-800">
                @auth
                    @if(auth()->user()->hasPermission('access-dashboard'))
                    <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-speedometer2 mr-2"></i>{{ __('translation.navigation.dashboard') }}
                    </a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <i class="bi bi-person mr-2"></i>{{ __('translation.profile') }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <i class="bi bi-box-arrow-right mr-2"></i>{{ __('translation.logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="block px-3 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">
                        {{ __('translation.login') }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- Entries in the "More" menu: as a list instead of pills, submenus visible and indented --}}
<style>
    .modern-more-list > a,
    .modern-more-list > .modern-dropdown > button { display: flex; width: 100%; justify-content: flex-start; border-radius: 0.5rem; }
    .modern-more-list > .modern-dropdown > button svg { display: none; }
    .modern-more-list .modern-dropdown-menu { position: static !important; display: block !important; opacity: 1 !important; transform: none !important; width: auto; margin: 0 0 0.25rem 0.75rem; border: 0; box-shadow: none; background: transparent; padding: 0; }
</style>

<script @cspNonce>
// "More": trailing entries that do not fit on the line (as in the default theme)
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.querySelector('[data-modern-nav]');
    var more = nav ? nav.querySelector('[data-modern-more]') : null;
    var moreList = more ? more.querySelector('[data-modern-more-list]') : null;
    if (!nav || !more || !moreList) return;
    var fit = function () {
        while (moreList.firstElementChild) nav.insertBefore(moreList.firstElementChild, more);
        more.hidden = true;
        if (window.innerWidth < 1024 || nav.scrollWidth <= nav.clientWidth + 1) return;
        more.hidden = false;
        var items = Array.prototype.slice.call(nav.children).filter(function (el) { return el !== more; });
        while (items.length > 1 && nav.scrollWidth > nav.clientWidth + 1) {
            moreList.insertBefore(items.pop(), moreList.firstElementChild);
        }
    };
    var pending = false;
    window.addEventListener('resize', function () {
        if (pending) return;
        pending = true;
        window.requestAnimationFrame(function () { pending = false; fit(); });
    });
    fit();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
});

function modernNavToggle() {
    var menu = document.getElementById('modern-nav-mobile-menu');
    var btn = document.getElementById('modern-nav-toggle');
    var isOpen = menu.classList.contains('is-open');
    menu.classList.toggle('is-open', !isOpen);
    menu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
    menu.inert = isOpen;
    btn.classList.toggle('is-active', !isOpen);
    btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
}

function modernToggleSubmenu(btn) {
    var panel = btn.nextElementSibling;
    var isOpen = panel.style.display !== 'none';
    var chevron = btn.querySelector('svg');
    if (isOpen) {
        panel.style.display = 'none';
        chevron.style.transform = '';
        btn.setAttribute('aria-expanded', 'false');
    } else {
        panel.style.display = 'block';
        chevron.style.transform = 'rotate(180deg)';
        btn.setAttribute('aria-expanded', 'true');
    }
}

// Desktop dropdown menus
document.querySelectorAll('.modern-dropdown').forEach(function(wrapper) {
    var btn = wrapper.querySelector('button');
    var menu = wrapper.querySelector('.modern-dropdown-menu');
    var open = false;

    function show() {
        open = true;
        menu.style.display = 'block';
        btn.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                menu.style.opacity = '1';
                menu.style.transform = 'translateY(0)';
            });
        });
    }
    function hide() {
        open = false;
        menu.style.opacity = '0';
        menu.style.transform = 'translateY(-4px)';
        btn.setAttribute('aria-expanded', 'false');
        setTimeout(function() { if (!open) menu.style.display = 'none'; }, 150);
    }

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        open ? hide() : show();
    });
    menu.addEventListener('click', function() { hide(); });
    document.addEventListener('click', function(e) {
        if (!wrapper.contains(e.target)) hide();
    });
});

document.addEventListener('DOMContentLoaded', function() {
    var navToggleBtn = document.getElementById('modern-nav-toggle');
    if (navToggleBtn) navToggleBtn.addEventListener('click', function() { modernNavToggle(); });

    var searchToggle = document.querySelector('[data-csp="nav-search-toggle"]');
    if (searchToggle) searchToggle.addEventListener('click', function() { toggleSearchOverlay(); });

    document.querySelectorAll('[data-csp="modern-submenu-toggle"]').forEach(function(btn) {
        btn.addEventListener('click', function() { modernToggleSubmenu(btn); });
    });
});
</script>
