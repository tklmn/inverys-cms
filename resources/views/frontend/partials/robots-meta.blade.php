{{--
    Search engines blocked (Settings → SEO): noindex for every
    frontend page. Goes into the <head> of every theme as a head hook (including
    Composer themes); the X-Robots-Tag header (BlockSearchIndexing) applies as well.
    The backend includes head hooks too — the line is omitted there.
--}}
@if(\App\Http\Middleware\BlockSearchIndexing::applies(request()))
    <meta name="robots" content="{{ \App\Support\SeoSettings::NOINDEX }}">
@endif
