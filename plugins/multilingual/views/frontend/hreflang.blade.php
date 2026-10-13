{{--
    hreflang in the <head>: tells search engines which URL belongs to which
    language. x-default points to the default language.
--}}
@if(app(\App\Support\Locales::class)->isMultilingual())
@foreach(\Plugins\Multilingual\Support\Alternates::all() as $code => $alt)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ $alt['url'] }}">
@endforeach
    <link rel="alternate" hreflang="x-default" href="{{ \Plugins\Multilingual\Support\Alternates::defaultUrl() }}">
@endif
