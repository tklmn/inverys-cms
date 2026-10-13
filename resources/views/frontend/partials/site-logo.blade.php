{{--
    Website logo (header, error pages).

    Custom logo from the theme customizer, optionally with a version for
    dark mode. If it is the bundled images/logo.svg and there is no dark
    version, the SVG is embedded inline: that way dark mode can lighten
    the Indigo to Paper via CSS (site.css) — as an <img> it would be
    barely readable on a dark background.

    @include('frontend.partials.site-logo', ['logo' => …, 'logoDark' => …, 'siteName' => …])
--}}
@php
    $bundled = $logo && ltrim((string) $logo, '/') === 'images/logo.svg' && is_file(public_path('images/logo.svg'));
@endphp
@if($logo && $bundled && ! $logoDark)
    {{-- As in Inverys Forum: wordmark followed by a small "CMS" (part of the logo, not translated) --}}
    <span class="site-logo-lockup">
        <span class="site-logo-svg" role="img" aria-label="{{ $siteName }}">{!! file_get_contents(public_path('images/logo.svg')) !!}</span>
        <span class="site-logo-product" aria-hidden="true">CMS</span>
    </span>
@elseif($logo)
    <img src="{{ asset($logo) }}" alt="{{ $siteName }}" class="site-logo-light {{ $logoDark ? 'has-dark' : '' }}">
    @if($logoDark)
        <img src="{{ asset($logoDark) }}" alt="{{ $siteName }}" class="site-logo-dark">
    @endif
@else
    <x-application-logo class="h-8 w-auto fill-current text-[var(--site-heading)]" />
@endif
