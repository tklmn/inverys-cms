<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full{{ \App\Helpers\ThemeHelper::getTheme() === 'dark' ? ' dark' : '' }}">
    @include('backend.layouts.partials.head')
    {{-- Full-screen editor (x-editor-layout): no side menu, no header —
         the editor's bar brings its own back, status and save --}}
    <body class="h-full overflow-hidden antialiased">
        <a href="#editor-main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-2 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2 focus:text-sm focus:text-accent">
            {{ __('Skip to main content') }}
        </a>

        {{ $slot }}

        <x-ui.toasts />

        <x-ui.panel />

        @include('backend.partials.delete-modal')
        @include('backend.partials.confirm-modal')
        @include('backend.partials.media-picker-modal')
        @include('backend.layouts.partials.scripts')
    </body>
</html>
