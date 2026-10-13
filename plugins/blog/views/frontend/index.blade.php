{{--
    Post overview. Styled with the website building blocks (site-card,
    site-post …, resources/css/frontend/components/site.css) so that it fits
    every theme and both colour modes. The blog plugin settings
    (columns, date, author, excerpt, sidebar …) apply
    unchanged.

    One column: posts as wide rows (image left, text right), the
    newest on page 1 larger as the lead. Several columns: cards.
    The whole card is clickable (title link stretched over the area).
--}}
@php
    $pageTitle = isset($currentCategory)
        ? __('blog::blog.posts_in_category', ['category' => $currentCategory->name])
        : __('blog::blog.title');

    $cols = (int) ($columns ?? 1);
    $gridClass = match(true) {
        $cols >= 3 => 'grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3',
        $cols === 2 => 'grid grid-cols-1 gap-6 sm:grid-cols-2',
        default => 'flex flex-col gap-6',
    };
    $wide = $cols <= 1;

    // Reading time: about 200 words per minute
    $readingTime = fn ($post) => max(1, (int) ceil(count(preg_split('/\s+/u', trim(strip_tags((string) $post->content)), -1, PREG_SPLIT_NO_EMPTY)) / 200));
@endphp
<x-frontend-app-layout>
    <x-slot name="title">{{ $pageTitle }}</x-slot>

    <x-slot name="header">
        @if(isset($currentCategory))
            <a href="{{ route('blog.index') }}" class="site-breadcrumb !mb-4 inline-flex hover:text-[var(--primary-text)]">
                <x-ui.icon name="arrow-left" /> {{ __('blog::blog.back_to_blog') }}
            </a>
        @endif
        <h1 class="site-page-title">{{ $pageTitle }}</h1>
        @if(isset($currentCategory) && $currentCategory->description)
            <p class="site-page-lead">{{ $currentCategory->description }}</p>
        @endif
    </x-slot>

    {{--
        Full width like the homepage content: categories and RSS sit in a bar
        above the list instead of a sidebar (which took up a third).
        On phones it swipes sideways instead of wrapping.
    --}}
    @if(($show_sidebar ?? true) && ($categories->isNotEmpty() || ($show_rss_link ?? true)))
        <nav class="site-chip-bar mb-10" aria-label="{{ __('blog::blog.categories') }}">
            @if($categories->isNotEmpty())
                <a href="{{ route('blog.index') }}"
                   @unless(isset($currentCategory)) aria-current="page" @endunless
                   class="site-chip {{ isset($currentCategory) ? '' : 'is-active' }}">{{ __('blog::blog.all_posts') }}</a>
                @foreach($categories as $cat)
                    @php
                        $current = isset($currentCategory) && $currentCategory->id === $cat->id;
                    @endphp
                    <a href="{{ route('blog.category', $cat->slug) }}"
                       @if($current) aria-current="page" @endif
                       class="site-chip {{ $current ? 'is-active' : '' }}">
                        {{ $cat->name }}
                        <span class="site-chip-count">{{ $cat->posts_count }}</span>
                    </a>
                @endforeach
            @endif

            @if($show_rss_link ?? true)
                <a href="{{ route('blog.feed') }}" class="site-chip site-chip-end">
                    <x-ui.icon name="rss" class="text-orange-500" /> {{ __('blog::blog.rss_feed') }}
                </a>
            @endif
        </nav>
    @endif

    <div class="{{ $gridClass }}">
        @forelse($posts as $post)
            @php
                $url = route('blog.show', $post->slug);
                $lead = $wide && $loop->first && $posts->onFirstPage() && ! isset($currentCategory);
                $image = ($show_featured_image ?? true) && $post->featured_image;
            @endphp
            <article class="site-card site-post group {{ $wide ? 'site-post-row' : '' }} {{ $lead ? 'site-post-lead' : '' }} {{ $image ? '' : 'site-post-no-media' }}">
                @if($image)
                    <div class="site-post-media">
                        <img src="{{ asset('storage/'.$post->featured_image) }}" alt="" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    </div>
                @endif

                <div class="site-card-body site-post-body">
                    @if(($show_categories ?? true) && $post->categories->isNotEmpty())
                        <div class="site-post-cats">
                            @foreach($post->categories as $cat)
                                <a href="{{ route('blog.category', $cat->slug) }}" class="site-badge hover:bg-[var(--primary)] hover:text-white">{{ $cat->name }}</a>
                            @endforeach
                        </div>
                    @endif

                    <h2 class="site-post-title">
                        <a href="{{ $url }}" class="site-stretched-link">{{ $post->title }}</a>
                    </h2>

                    @if($show_excerpt ?? true)
                        @if($post->excerpt || $post->meta_description)
                            <p class="site-post-excerpt">{{ $post->excerpt ?: $post->meta_description }}</p>
                        @endif
                    @endif

                    @if(($show_date ?? true) || ($show_author ?? true))
                        <p class="site-post-meta">
                            @if(($show_author ?? true) && $post->author)
                                <span class="site-post-author">
                                    <span class="site-avatar" aria-hidden="true">{{ Str::upper(Str::substr((string) $post->author->full_name, 0, 1)) }}</span>
                                    {{ $post->author->full_name }}
                                </span>
                            @endif
                            @if(($show_date ?? true) && $post->published_at)
                                <time datetime="{{ $post->published_at->toDateString() }}">{{ format_date_long($post->published_at) }}</time>
                            @endif
                            <span>{{ __('blog::blog.reading_time', ['minutes' => $readingTime($post)]) }}</span>
                        </p>
                    @endif

                    @if($show_read_more ?? true)
                        <span class="site-link-arrow site-post-more" aria-hidden="true">
                            {{ ! empty($read_more_text) ? $read_more_text : __('blog::blog.read_more') }}
                            <x-ui.icon name="arrow-right" />
                        </span>
                    @endif
                </div>
            </article>
        @empty
            <div class="site-card col-span-full items-center px-6 py-14 text-center">
                <x-ui.icon name="file-text" class="mb-3 text-4xl text-[var(--site-subtle)]" />
                <p class="text-[var(--site-muted)]">{{ __('blog::blog.no_posts') }}</p>
            </div>
        @endforelse
    </div>

    @if(($show_pagination ?? true) && $posts->hasPages())
        <div class="mt-12">{{ $posts->links('frontend.partials.pagination') }}</div>
    @endif
</x-frontend-app-layout>
