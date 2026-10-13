{{--
    hreflang in the <head>: tells search engines which URL belongs to which
    language — only the languages the page really exists in (original plus
    translations), codes in BCP 47 form (pt-BR). x-default points to the
    default language. Left out on error pages, noindex pages, pages with a
    canonical address elsewhere and on fallback URLs of untranslated content.
--}}
@php($seo = app(\App\Support\Seo\SeoContext::class))
@if($seo->emitsAlternates())
@foreach($seo->alternates() as $code => $url)
    <link rel="alternate" hreflang="{{ \App\Support\Seo\LanguageCodes::hreflang((string) $code) }}" href="{{ $url }}">
@endforeach
@if($seo->defaultAlternate())
    <link rel="alternate" hreflang="x-default" href="{{ $seo->defaultAlternate() }}">
@endif
@endif
