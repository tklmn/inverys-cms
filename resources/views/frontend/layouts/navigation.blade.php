<nav id="main-nav" class="bg-white dark:bg-slate-800 shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ url('/') }}">
                        @if(theme('logo_url'))
                            <img src="{{ asset(theme('logo_url')) }}" alt="{{ __('translation.app_name') }}" class="block h-8 w-auto">
                        @else
                            <x-application-logo class="block h-8 w-auto fill-current text-gray-800 dark:text-white" />
                        @endif
                    </a>
                </div>

                <!-- Navigation Links (desktop) -->
                <div class="hidden space-x-8 lg:-my-px lg:ms-10 lg:flex">
                    @include('frontend.partials.menu', ['pages' => $menuPages])
                </div>
            </div>

            <!-- User Menu (desktop) -->
            <div class="hidden lg:flex lg:items-center lg:ms-6 lg:space-x-1">
                @if(theme('show_search', true))
                <button type="button" data-csp="nav-search-toggle" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.search') }}">
                    <i class="bi bi-search text-lg"></i>
                    <span class="sr-only">{{ __('translation.search') }}</span>
                </button>
                @endif
                @auth
                    @if(auth()->user()->hasPermission('access-dashboard'))
                    <a href="{{ route('dashboard') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.navigation.dashboard') }}">
                        <i class="bi bi-speedometer2 text-lg"></i>
                        <span class="sr-only">{{ __('translation.navigation.dashboard') }}</span>
                    </a>
                    @endif
                    @if(theme('show_profile', true))
                    <a href="{{ route('profile.edit') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.profile') }}">
                        <i class="bi bi-person text-lg"></i>
                        <span class="sr-only">{{ __('translation.profile') }}</span>
                    </a>
                    @endif
                    @if(theme('show_logout', true))
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.logout') }}">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                            <span class="sr-only">{{ __('translation.logout') }}</span>
                        </button>
                    </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.login') }}">
                        <i class="bi bi-box-arrow-in-right text-lg"></i>
                        <span class="sr-only">{{ __('translation.login') }}</span>
                    </a>
                    @if (Route::has('register') && registration_open())
                        <a href="{{ route('register') }}" class="w-9 h-9 inline-flex items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors" title="{{ __('translation.register') }}">
                            <i class="bi bi-person-plus text-lg"></i>
                            <span class="sr-only">{{ __('translation.register') }}</span>
                        </a>
                    @endif
                @endauth
            </div>

            <!-- Mobile Menu Button -->
            <div class="-me-2 flex items-center lg:hidden">
                <button id="nav-mobile-toggle" class="nav-hamburger inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none transition duration-150 ease-in-out" aria-expanded="false" aria-controls="nav-mobile-menu" aria-label="{{ __('translation.frontend.toggle_navigation') }}">
                    <span class="nav-hamburger-line"></span>
                    <span class="nav-hamburger-line"></span>
                    <span class="nav-hamburger-line"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="nav-mobile-menu" class="nav-mobile-panel lg:hidden" aria-hidden="true" inert>
        <div class="nav-mobile-panel-inner">
            <div class="pt-2 pb-3 space-y-1">
                @foreach($menuPages as $page)
                    @php
                        $mIsExternal = !empty($page->external_url);
                        $mLinkUrl = $mIsExternal
                            ? $page->external_url
                            : ($page->is_root ? route('home') : route('page.show', $page->slug));
                        $mIsActive = !$mIsExternal && (
                            ($page->is_root && request()->is('/')) ||
                            request()->is($page->slug) || request()->is($page->slug . '/*')
                        );
                        $mTarget = ($mIsExternal && $page->external_url_new_window) ? '_blank' : null;
                    @endphp
                    <x-responsive-nav-link :href="$mLinkUrl" :active="$mIsActive" :target="$mTarget">
                        {{ $page->nav_title ?: $page->title }}
                    </x-responsive-nav-link>
                    @if(!$mIsExternal && $page->children->where('hide_in_menu', false)->count())
                        @foreach($page->children->where('hide_in_menu', false) as $child)
                            @php
                                $mcIsExternal = !empty($child->external_url);
                                $mcUrl = $mcIsExternal ? $child->external_url : route('page.show', $child->slug);
                                $mcIsActive = !$mcIsExternal && (request()->is($child->slug) || request()->is($child->slug . '/*'));
                                $mcTarget = ($mcIsExternal && $child->external_url_new_window) ? '_blank' : null;
                            @endphp
                            <x-responsive-nav-link :href="$mcUrl" :active="$mcIsActive" class="pl-8" :target="$mcTarget">
                                {{ $child->nav_title ?: $child->title }}
                            </x-responsive-nav-link>
                        @endforeach
                    @endif
                @endforeach
            </div>

            <!-- Authentication Links -->
            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
                @auth
                    <div class="px-4">
                        <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                        <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                    </div>
                    <div class="mt-3 space-y-1">
                        @if(auth()->user()->hasPermission('access-dashboard'))
                        <x-responsive-nav-link :href="route('dashboard')">
                            <i class="bi bi-speedometer2 mr-1 inline-block align-text-bottom"></i> {{ __('translation.navigation.dashboard') }}
                        </x-responsive-nav-link>
                        @endif
                        <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                            <i class="bi bi-person mr-1 inline-block align-text-bottom"></i> {{ __('translation.profile') }}
                        </x-responsive-nav-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-responsive-nav-link :href="route('logout')" data-csp="nav-logout">
                                <i class="bi bi-box-arrow-right mr-1 inline-block align-text-bottom"></i> {{ __('translation.logout') }}
                            </x-responsive-nav-link>
                        </form>
                    </div>
                @else
                    <div class="space-y-1">
                        <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">
                            <i class="bi bi-box-arrow-in-right mr-1 inline-block align-text-bottom"></i> {{ __('translation.login') }}
                        </x-responsive-nav-link>
                        @if (Route::has('register') && registration_open())
                            <x-responsive-nav-link :href="route('register')" :active="request()->routeIs('register')">
                                <i class="bi bi-person-plus mr-1 inline-block align-text-bottom"></i> {{ __('translation.register') }}
                            </x-responsive-nav-link>
                        @endif
                    </div>
                @endauth
            </div>
        </div>
    </div>
</nav>

<script @cspNonce>
function navToggleMobile() {
    var menu = document.getElementById('nav-mobile-menu');
    var btn = document.getElementById('nav-mobile-toggle');
    var isOpen = menu.classList.contains('is-open');
    menu.classList.toggle('is-open', !isOpen);
    menu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
    menu.inert = isOpen;
    btn.classList.toggle('is-active', !isOpen);
    btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
}

document.addEventListener('DOMContentLoaded', function() {
    var searchToggle = document.querySelector('[data-csp="nav-search-toggle"]');
    if (searchToggle) searchToggle.addEventListener('click', function() { toggleSearchOverlay(); });

    var mobileToggle = document.getElementById('nav-mobile-toggle');
    if (mobileToggle) mobileToggle.addEventListener('click', function() { navToggleMobile(); });

    document.querySelectorAll('[data-csp="nav-logout"]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            el.closest('form').submit();
        });
    });
});
</script>
