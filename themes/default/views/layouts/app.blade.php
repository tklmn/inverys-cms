@php
    // "Title – Site" by default; order and separator from Settings → SEO, "– Page N" on lists
    $documentTitle = seo_title($title ?? null);

    // Theme settings (customizer) → CSS variables and attributes on <body>,
    // evaluated in resources/css/frontend/components/site-design.css.
    // Only let known values through — the values end up in the markup.
    $pick = fn (string $key, array $allowed, string $default) => in_array((string) theme($key, $default), $allowed, true) ? (string) theme($key, $default) : $default;
    $color = fn (string $key, string $default) => preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) theme($key, $default)) ? (string) theme($key, $default) : $default;

    $themeAttributes = [
        'data-header' => $pick('header_style', ['glass', 'solid', 'static'], 'glass'),
        'data-footer' => $pick('footer_style', ['dark', 'light'], 'dark'),
        'data-radius' => $pick('corner_style', ['sharp', 'rounded', 'round'], 'rounded'),
        'data-font' => $pick('font_family', ['inter', 'outfit', 'system', 'serif'], 'inter'),
        'data-dark' => $pick('dark_background', ['navy', 'black', 'slate'], 'navy'),
        'data-glow' => theme('background_glow', true) ? '1' : '0',
    ];
    $maxWidth = ['narrow' => '68rem', 'normal' => '80rem', 'wide' => '92rem'][$pick('content_width', ['narrow', 'normal', 'wide'], 'normal')];

    // The text font is needed on every page: load it right away instead of after the CSS
    $fontFile = ['inter' => 'inter/files/inter-latin-wght-normal.woff2', 'outfit' => 'outfit/files/outfit-latin-wght-normal.woff2'][$themeAttributes['data-font']] ?? null;
    $fontPreload = $fontFile ? rescue(fn () => \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/'.$fontFile), null, false) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light dark">

        <title>{{ $documentTitle }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset(setting('favicon', 'images/favicon.svg')) }}">

        @isset($meta)
            {{ $meta }}
        @endisset

        @if($fontPreload)
            <link rel="preload" href="{{ $fontPreload }}" as="font" type="font/woff2" crossorigin>
        @endif

        {{-- Bootstrap Icons: for older content and plugins that still use them — loaded without blocking the first paint --}}
        <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}" media="print" id="bootstrap-icons-css">
        <script @cspNonce>(function (l) { if (l.sheet) { l.media = 'all'; } else { l.addEventListener('load', function () { l.media = 'all'; }); } })(document.getElementById('bootstrap-icons-css'));</script>
        <noscript><link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}"></noscript>

        @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
        {{-- Primary colour from the theme settings; all accents derive from it (site.css) --}}
        <style>
            :root { --primary: {{ $color('primary_color', '#4f46e5') }}; --accent: {{ $color('accent_color', '#9333ea') }}; --site-max-width: {{ $maxWidth }}; }
            .text-white .block-heading { color: inherit; }
            .text-white .block-text.prose { --tw-prose-body: rgb(255 255 255 / 0.9); --tw-prose-headings: white; --tw-prose-bold: white; --tw-prose-links: white; }
        </style>
        @if(theme_custom_css())
            <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
        @endif
    </head>
    <body class="site-body antialiased" @foreach($themeAttributes as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-[var(--site-surface-raised)] focus:px-4 focus:py-3 focus:shadow-lg">
            {{ __('Skip to main content') }}
        </a>

        <div class="site-canvas flex min-h-screen flex-col">
            @include('theme::layouts.navigation')

            {{-- Page header and header image sit inside <main>: a <header> directly
                 in <body> would be a second "banner" landmark next to the site
                 header, and the skip link should land on the page title. --}}
            @php($mainLabelText = isset($mainLabel) ? html_entity_decode(trim((string) $mainLabel), ENT_QUOTES | ENT_HTML5) : '')
            <main id="main-content" class="flex-1" @if($mainLabelText !== '') aria-label="{{ $mainLabelText }}" @endif>
                {{-- Page header: breadcrumb, title, subtitle --}}
                @isset($header)
                    @if(theme('show_page_title', true) && trim((string) $header) !== '')
                        <header class="site-page-head">
                            <div class="site-container">
                                {{ $header }}
                            </div>
                        </header>
                    @endif
                @endisset

                @isset($headerImage)
                    {{-- Alt text from the media library (current language); empty for
                         decorative images and for images outside the library --}}
                    @php($headerMedia = \App\Models\Media::findByPath((string) $headerImage))
                    <div class="site-container pt-8">
                        {{-- Above the fold: high priority (the page's blocks then load lazily) --}}
                        <x-responsive-image :src="trim((string) $headerImage)" :priority="true" alt="{{ $headerMedia?->altAttribute() ?? '' }}" class="h-64 w-full rounded-[var(--site-radius-lg)] object-cover shadow-[var(--site-shadow-lg)] sm:h-80" />
                    </div>
                @endisset

                <div class="{{ ((string) ($template ?? 'container')) === 'full_width' ? '' : 'site-container py-10 sm:py-14' }}">
                    {{ $slot }}
                </div>
            </main>

            @include('theme::layouts.footer')
        </div>

        @include('theme::partials.search-overlay')

        @if(theme('show_scroll_to_top', true))
            <button id="scroll-to-top" type="button" class="site-to-top" aria-label="{{ __('Scroll to top') }}">
                <x-ui.icon name="arrow-up" />
            </button>
            <script @cspNonce>
                (function () {
                    var btn = document.getElementById('scroll-to-top');
                    window.addEventListener('scroll', function () {
                        btn.classList.toggle('is-visible', window.scrollY > 400);
                    }, { passive: true });
                    btn.addEventListener('click', function () {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                })();
            </script>
        @endif
    </body>
</html>
