{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ setting('site_name', config('app.name')) }} — {{ __('blog::blog.title') }}</title>
        <link>{{ route('blog.index') }}</link>
        <description>{{ __('blog::blog.feed_description', ['site' => setting('site_name', config('app.name'))]) }}</description>
        <language>{{ app()->getLocale() }}</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRssString() ?? now()->toRssString() }}</lastBuildDate>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        @foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('blog.show', $post->slug) }}</link>
            <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
            <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
            @if($post->author)
            <author>noreply@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }} ({{ $post->author->full_name }})</author> {{-- i18n-ignore: Adresse --}}
            @endif
            <description>{{ $post->excerpt ?? $post->meta_description ?? '' }}</description>
        </item>
        @endforeach
    </channel>
</rss>
