<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5) . ' - ' . setting('site_name', __('translation.app_name')) : setting('site_name', __('translation.app_name')) }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset(setting('favicon', 'images/favicon.svg')) }}">

        @isset($meta)
            {{ $meta }}
        @endisset

        <!-- Bootstrap Icons -->
        <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/frontend/app.css', 'resources/js/frontend/app.js'])
        <style>
            :root { --primary: {{ theme('primary_color', '#4f46e5') }}; }
            a:not([class]) { color: var(--primary); }
            .text-white .block-heading { color: inherit; }
            .text-white .block-text.prose { --tw-prose-body: rgb(255 255 255 / 0.9); --tw-prose-headings: white; --tw-prose-bold: white; --tw-prose-links: white; }
        </style>
        @if(theme_custom_css())
            <style id="theme-custom-css">{!! theme_custom_css() !!}</style>
        @endif
    </head>
    <body class="font-sans antialiased dark:text-gray-100">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:p-4 focus:bg-white focus:text-blue-600 dark:focus:bg-slate-800 dark:focus:text-blue-400">
            {{ __('Skip to main content') }}
        </a>
        <div class="min-h-screen bg-white dark:bg-slate-900">
            @include('frontend.layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                @if(theme('show_page_title', true))
                <header class="bg-gray-50 dark:bg-slate-800">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                        {{ $header }}
                    </div>
                </header>
                @endif
            @endisset

            <!-- Header Image -->
            @isset($headerImage)
                <div class="w-full">
                    <img src="{{ asset($headerImage) }}" alt="" class="w-full h-64 sm:h-80 object-cover">
                </div>
            @endisset

            <!-- Page Content -->
            <main id="main-content" class="min-h-[75vh]">
                <div class="{{ ((string)($template ?? 'container')) === 'full_width' ? '' : 'max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8' }}">
                    {{ $slot }}
                </div>
            </main>

            <!-- Footer -->
            @include('frontend.layouts.footer')
        </div>
        @include('frontend.partials.search-overlay')
        @if(theme('show_scroll_to_top', true))
            <button id="scroll-to-top"
                class="fixed bottom-6 right-6 z-40 hidden w-11 h-11 items-center justify-center rounded-full bg-gray-800/80 dark:bg-white/20 text-white backdrop-blur shadow-lg hover:bg-gray-900 dark:hover:bg-white/30 transition-all"
                aria-label="{{ __('Scroll to top') }}"
            >
                <i class="bi bi-chevron-up text-lg"></i>
            </button>
            <script @cspNonce>
                (function() {
                    const btn = document.getElementById('scroll-to-top');
                    window.addEventListener('scroll', function() {
                        btn.classList.toggle('hidden', window.scrollY < 300);
                        btn.classList.toggle('inline-flex', window.scrollY >= 300);
                    });
                    btn.addEventListener('click', function() {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                })();
            </script>
        @endif
    </body>
</html>
