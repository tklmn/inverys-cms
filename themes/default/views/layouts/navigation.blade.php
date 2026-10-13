{{--
    Header: fixed and slightly translucent. Logo and menu on the left; on the
    right light/dark, plugin hooks (language menu), search and account.
    Below 64rem (phone, small tablet) a menu button folds everything into a
    panel. On desktop the menu stays visible; whatever does not fit goes under "More".
--}}
@php
    $linkFor = fn ($page) => ! empty($page->external_url)
        ? $page->external_url
        : ($page->is_root ? route('home') : route('page.show', $page->slug));
    $isCurrent = fn ($page) => empty($page->external_url) && ($page->is_root ? request()->is('/') : request()->is($page->slug));

    // Customizer: own logo (optionally with a dark mode version), main button, login
    $siteName = setting('site_name', __('translation.app_name'));
    $logo = theme('logo_url');
    $logoDark = theme('logo_url_dark');
    $headerButton = trim((string) theme('header_button_text', ''));
    $headerButtonUrl = \App\Helpers\HtmlHelper::safeUrl(theme('header_button_url', '/contact'));
    $showLogin = (bool) theme('show_login', true);
@endphp

<header id="main-nav" class="site-header">
    <div class="site-container">
        <div class="site-header-inner">
            <a href="{{ url('/') }}" class="site-logo flex shrink-0 items-center" aria-label="{{ $siteName }}">
                @include('frontend.partials.site-logo', ['logo' => $logo, 'logoDark' => $logoDark, 'siteName' => $siteName])
            </a>

            <nav class="site-nav" aria-label="{{ __('translation.navigation.main') }}">
                @include('theme::partials.menu', ['pages' => $menuPages])
            </nav>

            <div class="site-actions">
                @if(theme('show_theme_toggle', true))
                    <x-ui.theme-toggle button-class="site-icon-btn" />
                @endif

                {{-- Hook for plugins (e.g. language menu) --}}
                @foreach(app('plugin.navigation_hooks') as $navHook)
                    @includeIf($navHook)
                @endforeach

                @if(theme('show_search', true))
                    <button type="button" data-csp="nav-search-toggle" class="site-icon-btn" aria-label="{{ __('translation.search') }}"
                            title="{{ __('translation.search') }} ({{ __('translation.frontend.search_shortcut') }})"
                            aria-keyshortcuts="Control+K" data-shortcut="{{ __('translation.frontend.search_shortcut') }}">
                        <x-ui.icon name="search" />
                    </button>
                @endif

                <div class="site-actions-desktop">
                    @auth
                        @if(auth()->user()->hasPermission('access-dashboard'))
                            <a href="{{ route('dashboard') }}" class="site-icon-btn" title="{{ __('translation.navigation.dashboard') }}" aria-label="{{ __('translation.navigation.dashboard') }}">
                                <x-ui.icon name="layout-dashboard" />
                            </a>
                        @endif
                        @if(theme('show_profile', true))
                            <a href="{{ route('profile.edit') }}" class="site-icon-btn" title="{{ __('translation.profile') }}" aria-label="{{ __('translation.profile') }}">
                                <x-ui.icon name="user" />
                            </a>
                        @endif
                        @if(theme('show_logout', true))
                            <form method="POST" action="{{ route('logout') }}" class="inline-flex">
                                @csrf
                                <button type="submit" class="site-icon-btn" title="{{ __('translation.logout') }}" aria-label="{{ __('translation.logout') }}">
                                    <x-ui.icon name="log-out" />
                                </button>
                            </form>
                        @endif
                    @else
                        @if($showLogin)
                            <a href="{{ route('login') }}" class="site-btn site-btn-secondary ml-2 !min-h-9 !px-4 !py-1.5">
                                <x-ui.icon name="log-in" />
                                {{ __('translation.login') }}
                            </a>
                        @endif
                    @endauth
                    @if($headerButton !== '')
                        <a href="{{ $headerButtonUrl }}" class="site-btn site-btn-primary ml-2 !min-h-9 !px-4 !py-1.5">{{ $headerButton }}</a>
                    @endif
                </div>

                <button id="nav-mobile-toggle" type="button" class="site-icon-btn site-menu-toggle" aria-expanded="false" aria-controls="nav-mobile-menu" aria-label="{{ __('translation.frontend.toggle_navigation') }}">
                    <span class="inline-flex" data-icon-closed><x-ui.icon name="menu" /></span>
                    <span class="inline-flex" data-icon-open hidden><x-ui.icon name="x" /></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div id="nav-mobile-menu" class="nav-mobile-panel lg:hidden" aria-hidden="true" inert>
        <div class="nav-mobile-panel-inner">
            <nav class="site-container flex flex-col gap-1 pb-4 pt-2" aria-label="{{ __('translation.navigation.main') }}">
                {{-- One group per entry (page + subpages); side by side from 48rem (compact mode on desktop) --}}
                <div class="site-mobile-groups">
                    @foreach($menuPages as $page)
                        @php
                            $mobileChildren = empty($page->external_url)
                                ? $page->children->where('is_active', true)->where('hide_in_menu', false)
                                : collect();
                        @endphp
                        <div class="site-mobile-group">
                            <a href="{{ $linkFor($page) }}" class="site-mobile-link" @if($isCurrent($page)) aria-current="page" @endif
                               @if(! empty($page->external_url) && $page->external_url_new_window) target="_blank" rel="noopener noreferrer" @endif>
                                {{ $page->nav_title ?: $page->title }}
                            </a>
                            @if($mobileChildren->isNotEmpty())
                                <div class="site-mobile-sub">
                                    @foreach($mobileChildren as $child)
                                        <a href="{{ $linkFor($child) }}" class="site-mobile-link" @if($isCurrent($child)) aria-current="page" @endif>
                                            {{ $child->nav_title ?: $child->title }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($headerButton !== '')
                    <a href="{{ $headerButtonUrl }}" class="site-btn site-btn-primary mt-2">{{ $headerButton }}</a>
                @endif

                <div class="mt-2 border-t border-[var(--site-border)] pt-3">
                    @auth
                        <div class="px-4 pb-2">
                            <div class="font-semibold text-[var(--site-heading)]">{{ Auth::user()->name }}</div>
                            <div class="text-sm text-[var(--site-subtle)]">{{ Auth::user()->email }}</div>
                        </div>
                        @if(auth()->user()->hasPermission('access-dashboard'))
                            <a href="{{ route('dashboard') }}" class="site-mobile-link"><x-ui.icon name="layout-dashboard" /> {{ __('translation.navigation.dashboard') }}</a>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="site-mobile-link" @if(request()->routeIs('profile.edit')) aria-current="page" @endif><x-ui.icon name="user" /> {{ __('translation.profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="site-mobile-link w-full text-left"><x-ui.icon name="log-out" /> {{ __('translation.logout') }}</button>
                        </form>
                    @elseif($showLogin)
                        <a href="{{ route('login') }}" class="site-mobile-link" @if(request()->routeIs('login')) aria-current="page" @endif><x-ui.icon name="log-in" /> {{ __('translation.login') }}</a>
                        @if(Route::has('register') && registration_open())
                            <a href="{{ route('register') }}" class="site-mobile-link" @if(request()->routeIs('register')) aria-current="page" @endif><x-ui.icon name="user-plus" /> {{ __('translation.register') }}</a>
                        @endif
                    @endauth
                </div>
            </nav>
        </div>
    </div>
</header>

<script @cspNonce>
document.addEventListener('DOMContentLoaded', function () {
    var searchToggle = document.querySelector('[data-csp="nav-search-toggle"]');
    if (searchToggle) searchToggle.addEventListener('click', function () { toggleSearchOverlay(); });

    var btn = document.getElementById('nav-mobile-toggle');
    var menu = document.getElementById('nav-mobile-menu');
    if (!btn || !menu) return;

    // If the menu does not fit on the line (many or long entries, e.g. in
    // German), the trailing entries move under "More" — instead of slipping
    // below the icons. The menu button is reserved for phones.
    var header = document.getElementById('main-nav');
    var nav = header.querySelector('.site-nav');
    var list = nav ? nav.querySelector('.site-nav-list') : null;
    var more = list ? list.querySelector('[data-nav-more]') : null;
    var moreList = more ? more.querySelector('[data-nav-more-list]') : null;
    var moreBtn = more ? more.querySelector('button') : null;
    var fit = function () {
        if (!list || !more) return;
        // Everything back onto the line, in the original order
        while (moreList.firstElementChild) list.insertBefore(moreList.firstElementChild, more);
        more.hidden = true;
        if (window.innerWidth < 1024 || list.scrollWidth <= nav.clientWidth + 1) return;

        more.hidden = false;
        var items = Array.prototype.slice.call(list.children, 0, -1);
        while (items.length > 1 && list.scrollWidth > nav.clientWidth + 1) {
            moreList.insertBefore(items.pop(), moreList.firstElementChild);
        }
        // Active page in the "More" menu: highlight the entry
        more.classList.toggle('is-active', !!moreList.querySelector('.is-active'));
    };
    if (moreBtn) {
        // Tapping opens and closes (touch has no hover)
        moreBtn.addEventListener('click', function () {
            var open = !more.classList.contains('is-open');
            more.classList.toggle('is-open', open);
            moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (event) {
            if (more.classList.contains('is-open') && !more.contains(event.target)) moreBtn.click();
        });
    }
    var pending = false;
    window.addEventListener('resize', function () {
        if (pending) return;
        pending = true;
        window.requestAnimationFrame(function () { pending = false; fit(); });
    });
    fit();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);

    btn.addEventListener('click', function () {
        var open = !menu.classList.contains('is-open');
        menu.classList.toggle('is-open', open);
        menu.setAttribute('aria-hidden', open ? 'false' : 'true');
        menu.inert = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.querySelector('[data-icon-closed]').hidden = open;
        btn.querySelector('[data-icon-open]').hidden = !open;
    });
});
</script>
