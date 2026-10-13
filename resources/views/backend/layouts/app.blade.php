<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    @include('backend.layouts.partials.head')
    <body class="h-full antialiased" data-nav="expanded">
        {{-- Set the navigation state before the first paint so nothing jumps --}}
        <script @cspNonce>
            if (window.innerWidth >= 1024 && localStorage.getItem('nav-collapsed') === '1') {
                document.body.dataset.nav = 'collapsed';
            }
        </script>

        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:text-accent">
            {{ __('Skip to main content') }}
        </a>

        <div class="flex h-screen overflow-hidden">
            @include('backend.layouts.partials.sidebar')

            <div class="app-frame flex min-w-0 flex-1 flex-col">
                @include('backend.layouts.partials.header')

                {{-- relative: reference point for everything absolute in the content. Without it
                     invisible inputs (switches, file buttons) align to the
                     window, fall out of the scroll area and lengthen
                     the whole page — empty space below the navigation. --}}
                <main id="main-content" class="relative flex-1 overflow-y-auto">
                    {{--
                        Full width with a narrow margin: the backend is a
                        work tool, not reading material. The margin hangs on
                        --spacing-gutter (tokens.css) and is the same everywhere.
                    --}}
                    <div class="w-full px-[var(--spacing-gutter)] py-[calc(var(--spacing-gutter)*0.9)]">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-ui.toasts />

        <x-ui.panel />

        @include('backend.partials.delete-modal')
        @include('backend.partials.confirm-modal')
        @include('backend.partials.media-picker-modal')
        @include('backend.layouts.partials.scripts')
    </body>
</html>
