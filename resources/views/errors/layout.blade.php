{{--
    Error pages (404, 403, 419, 429, 500, 503) in the style of the website.

    Deliberately standalone instead of in the theme layout: on 500/503 the
    database is often unreachable, and on a 404 outside any route neither
    the session nor the language middleware runs. Everything that accesses
    the database or session is therefore wrapped in rescue().

    @include('errors.layout', ['code' => 404])
--}}
@php
    $code = (int) ($code ?? 500);

    // Language: prefix of the URL (/de/…), otherwise the default language
    // (the multilingual plugin strips the prefix before routing — hence the original URL)
    rescue(function () {
        $locales = app(\App\Support\Locales::class);
        $path = ltrim(substr((string) parse_url((string) request()->server('REQUEST_URI'), PHP_URL_PATH), strlen(request()->getBasePath())), '/');
        $prefix = $locales->visitor() ?? strtok($path, '/');
        app()->setLocale($locales->has($prefix) ? $prefix : $locales->default());
    }, report: false);

    $siteName = rescue(fn () => setting('site_name', config('app.name')), config('app.name'), false);
    $dark = rescue(fn () => \App\Helpers\ThemeHelper::getTheme() === 'dark', false, false);
    $primary = rescue(fn () => theme('primary_color', '#4f46e5'), '#4f46e5', false);
    $primary = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $primary) ? $primary : '#4f46e5';
    $logo = rescue(fn () => theme('logo_url'), null, false);
    $logoDark = rescue(fn () => theme('logo_url_dark'), null, false);
    $home = rescue(fn () => url('/'), '/', false);
    $search = $code === 404 ? rescue(fn () => route('search'), null, false) : null;

    $known = [403, 404, 419, 429, 500, 503];
    $key = in_array($code, $known, true) ? $code : 500;

    // Without a build (missing manifest, deployment in progress) the error
    // page must not fail itself — otherwise Symfony's English fallback
    // page appears. A plain inline layout is enough then.
    $styles = rescue(fn () => (string) app(\Illuminate\Foundation\Vite::class)(['resources/css/frontend/app.css']), null, false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full{{ $dark ? ' dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <meta name="color-scheme" content="light dark">
        <title>{{ __('translation.errors.title_'.$key) }} – {{ $siteName }}</title>
        @if($styles)
            {!! $styles !!}
        @else
            <style>
                body { margin: 0; font-family: system-ui, sans-serif; color: #1e293b; background: #f8fafc; }
                .dark body { color: #e2e8f0; background: #0b1120; }
                .site-error { display: flex; flex-direction: column; align-items: center; min-height: 100vh; padding: 2rem 1rem; text-align: center; box-sizing: border-box; }
                .site-error-main { display: flex; flex: 1; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; max-width: 40rem; }
                .site-error-code { margin: 0; font-size: 6rem; font-weight: 800; line-height: 1; color: var(--primary); }
                .site-error-logo, .site-error-search, .ui-icon { display: none; }
                .site-btn { display: inline-block; padding: 0.625rem 1.25rem; border-radius: 0.5rem; background: var(--primary); color: #fff; text-decoration: none; }
                .site-error-foot { font-size: 0.875rem; opacity: 0.7; }
            </style>
        @endif
        <style>:root { --primary: {{ $primary }}; }</style>
    </head>
    <body class="site-body antialiased">
        <div class="site-canvas site-error">
            <a href="{{ $home }}" class="site-error-logo" aria-label="{{ $siteName }}">
                @include('frontend.partials.site-logo', ['logo' => $logo, 'logoDark' => $logoDark, 'siteName' => $siteName])
            </a>

            <main class="site-error-main">
                <p class="site-error-code site-text-gradient" aria-hidden="true">{{ $code }}</p>
                <h1 class="site-error-title">{{ __('translation.errors.title_'.$key) }}</h1>
                <p class="site-error-text">{{ __('translation.errors.text_'.$key) }}</p>

                @if($search)
                    <form action="{{ $search }}" method="GET" role="search" class="site-error-search">
                        <div class="site-search-box">
                            <x-ui.icon name="search" />
                            <input type="search" name="q" class="site-search-input" placeholder="{{ __('translation.search') }} …" aria-label="{{ __('translation.search') }}">
                            <button type="submit" class="site-btn site-btn-primary site-search-submit">
                                <x-ui.icon name="search" /><span class="site-search-submit-label">{{ __('translation.search') }}</span>
                            </button>
                        </div>
                    </form>
                @endif

                <div class="site-hero-actions">
                    @if($code === 419)
                        <a href="{{ url()->previous() }}" class="site-btn site-btn-primary site-btn-lg"><x-ui.icon name="rotate-ccw" />{{ __('translation.errors.reload') }}</a>
                    @endif
                    <a href="{{ $home }}" class="site-btn {{ $code === 419 ? 'site-btn-secondary' : 'site-btn-primary' }} site-btn-lg"><x-ui.icon name="house" />{{ __('translation.errors.home') }}</a>
                </div>
            </main>

            <p class="site-error-foot">&copy; {{ date('Y') }} {{ $siteName }}</p>
        </div>
    </body>
</html>
