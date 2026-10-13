{{--
    Layout for login, registration, forgotten password & co. (x-guest-layout).

    One column: logo, the form card below it, a gradient in the
    background. Light and dark via the tokens, as in the rest of the backend.

    Slots:
      title   — page heading (required)
      text    — short hint below it (optional)
      footer  — lines below the form, e.g. "No account yet?" (optional)

    Building blocks for the forms live in resources/views/auth/partials.
    Styling: resources/css/backend/components/auth.css
--}}
@php
    $siteName = setting('site_name', __('translation.app_name'));
    // Logo from the theme customizer (as on the website), otherwise the bundled one
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

    // The website's primary colour: login & co. look like the site you came from (auth.css)
    $brand = (string) theme('primary_color', '#4f46e5');
    $brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $brand) ? $brand : '#4f46e5';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    @include('backend.layouts.partials.head')
    <body class="h-full antialiased">
        <div class="auth-page has-brand" style="--auth-brand: {{ $brand }}">
            <div class="auth-topbar">                <x-ui.theme-toggle />
            </div>

            <main class="auth-center">
                <a href="{{ route('home') }}" class="auth-logo" aria-label="{{ $siteName }}">{!! $logo !!}</a>

                <div class="auth-card">
                    <header class="auth-head">
                        <h1 class="auth-title">{{ $title ?? '' }}</h1>
                        @isset($text)
                            <p class="auth-text">{{ $text }}</p>
                        @endisset
                    </header>

                    @include('auth.partials.status')

                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="auth-after">{{ $footer }}</div>
                @endisset
            </main>

            <p class="auth-copy">&copy; {{ date('Y') }} {{ $siteName }}</p>
        </div>
    </body>
</html>
