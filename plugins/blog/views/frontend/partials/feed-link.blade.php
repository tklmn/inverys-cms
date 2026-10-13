{{--
    Feed autodiscovery: browsers and feed readers find the RSS feed of the
    blog from every page. Head hook of the blog plugin; not on error pages.
--}}
@if(app(\App\Support\Seo\SeoContext::class)->indexableResponse() && Route::has('blog.feed'))
    <link rel="alternate" type="application/rss+xml" title="{{ setting('site_name', config('app.name')) }} – {{ __('blog::blog.title') }}" href="{{ route('blog.feed') }}">
@endif
