{{--
    SEO tags of every frontend page (App\Support\Seo\SeoHead): description,
    robots, canonical, Open Graph, Twitter and JSON-LD. Goes into the <head>
    of every theme as a head hook; controllers describe the page in
    App\Support\Seo\SeoContext. Tags a theme prints itself are skipped.
--}}
{!! app(\App\Support\Seo\SeoHead::class)->render() !!}
