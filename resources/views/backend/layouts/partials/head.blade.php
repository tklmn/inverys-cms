<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="asset-url" content="{{ asset('') }}">

    @php($routeTitle = request()->route()?->getMetadata('title'))
    <title>{{ $routeTitle ? __($routeTitle).' — '.__('translation.app_name') : __('translation.app_name') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset(setting('favicon', 'images/favicon.svg')) }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#1E1B4B">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap-icons.css') }}">

    {{-- Locale and texts for scripts (resources/js/backend/ui/i18n.js) --}}
    <script type="application/json" id="ui-i18n">@json(\App\Support\ScriptTexts::backend())</script>

    <!-- Scripts -->
    @vite(['resources/css/backend/app.css', 'resources/js/backend/app.js'])

    {{-- Plugin head hooks: only in the backend. The same head serves login,
         password reset, email change and the profile outside /backend —
         no plugin or tracking code there (App\Support\AccountPages). --}}
    @if(app()->bound('plugin.head_hooks') && request()->is('backend', 'backend/*'))
        @foreach(app('plugin.head_hooks') as $hookView)
            @includeIf($hookView)
        @endforeach
    @endif
</head>
