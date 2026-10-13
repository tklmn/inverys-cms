{{--
    Account area for people without backend access (/profile).

    Not the website theme — the UI building blocks (x-ui.card, x-ui.field …)
    do not exist there. Instead the same surface as the login (gradient,
    logo), only wider and with log out. Anyone allowed into the backend gets the
    backend layout (App\View\Components\DynamicLayout).
--}}
@php
    $siteName = setting('site_name', __('translation.app_name'));
    $themeLogo = theme('logo_url');
    $themeLogoDark = theme('logo_url_dark');
    // Bundled logo without a dark version: inlined so dark mode can lighten the lettering (auth.css)
    if ($themeLogo && ! $themeLogoDark && ltrim((string) $themeLogo, '/') === 'images/logo.svg') {
        $themeLogo = null;
    }
    $logo = $themeLogo
        ? '<img src="'.e(asset($themeLogo)).'" alt="'.e($siteName).'" class="auth-logo-light'.($themeLogoDark ? ' has-dark' : '').'">'
            .($themeLogoDark ? '<img src="'.e(asset($themeLogoDark)).'" alt="'.e($siteName).'" class="auth-logo-dark">' : '')
        : (file_exists(public_path('images/logo.svg'))
            // Bundled logo = Inverys wordmark: followed by a small "CMS"
            ? file_get_contents(public_path('images/logo.svg')).'<span class="brand-product" aria-hidden="true">CMS</span>'
            : e($siteName));

    // The website's primary colour, as on the login page (auth.css, .has-brand)
    $brand = (string) theme('primary_color', '#4f46e5');
    $brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brand) ? $brand : '#4f46e5';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    @include('backend.layouts.partials.head')
    <body class="h-full antialiased">
        <div class="auth-page account-page has-brand" style="--auth-brand: {{ $brand }}">
            <header class="account-bar">
                <a href="{{ route('home') }}" class="auth-logo account-logo" aria-label="{{ $siteName }}">{!! $logo !!}</a>

                <div class="flex items-center gap-1">                    <x-ui.theme-toggle />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-ui.button variant="ghost" size="md" icon="log-out" type="submit">{{ __('translation.logout') }}</x-ui.button>
                    </form>
                </div>
            </header>

            <main id="main-content" class="account-main">
                {{ $slot }}
            </main>

            <p class="auth-copy">&copy; {{ date('Y') }} {{ $siteName }}</p>
        </div>

        <x-ui.toasts />
    </body>
</html>
