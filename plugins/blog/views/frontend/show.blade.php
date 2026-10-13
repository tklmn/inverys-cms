{{-- Single post: header and image full width like the homepage, text wide but still readable --}}
<x-frontend-app-layout>
    <x-slot name="title">{{ $post->meta_title ?: $post->title }}</x-slot>
    {{-- Description, robots, canonical, Open Graph, article data and JSON-LD come from the core (SEO head hook, BlogController::describePost) --}}

    <x-slot name="header">
        <div class="max-w-5xl">
            @if($isPreview ?? false)
                @include('blog::partials.preview-banner')
            @endif

            @if($show_breadcrumbs ?? true)
                <nav class="site-breadcrumb" aria-label="{{ __('translation.frontend.breadcrumb') }}">
                    <a href="{{ route('blog.index') }}">{{ __('blog::blog.title') }}</a>
                    <x-ui.icon name="chevron-right" />
                    <span aria-current="page" class="truncate">{{ $post->title }}</span>
                </nav>
            @endif

            @if(($show_categories ?? true) && $post->categories->isNotEmpty())
                <div class="mb-4 flex flex-wrap gap-1.5">
                    @foreach($post->categories as $cat)
                        <a href="{{ route('blog.category', $cat->slug) }}" class="site-badge hover:bg-[var(--primary)] hover:text-white">{{ $cat->name }}</a>
                    @endforeach
                </div>
            @endif

            <h1 class="site-page-title">{{ $post->title }}</h1>

            @if($post->excerpt)
                <p class="site-page-lead">{{ $post->excerpt }}</p>
            @endif

            @if(($show_date ?? true) || ($show_author ?? true))
                @php
                    // Reading time: about 200 words per minute
                    $minutes = max(1, (int) ceil(count(preg_split('/\s+/u', trim(strip_tags((string) $post->content)), -1, PREG_SPLIT_NO_EMPTY)) / 200));
                @endphp
                <div class="site-post-byline">
                    @if(($show_author ?? true) && $post->author)
                        <span class="site-avatar site-avatar-lg" aria-hidden="true">{{ Str::upper(Str::substr((string) $post->author->full_name, 0, 1)) }}</span>
                    @endif
                    <div>
                        @if(($show_author ?? true) && $post->author)
                            <p class="font-semibold text-[var(--site-heading)]">{{ $post->author->full_name }}</p>
                        @endif
                        <p class="site-post-meta !mt-0 !pt-0">
                            @if(($show_date ?? true) && $post->published_at)
                                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->isScheduled() ? __('blog::blog.scheduled_for') : __('blog::blog.published_on') }} {{ format_date_long($post->published_at) }}</time>
                            @endif
                            <span>{{ __('blog::blog.reading_time', ['minutes' => $minutes]) }}</span>
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </x-slot>

    {{-- Without a page header (theme setting) the notice sits above the content --}}
    @if(($isPreview ?? false) && ! theme('show_page_title', true))
        @include('blog::partials.preview-banner')
    @endif

    @if(($show_featured_image ?? true) && $post->featured_image)
        {{-- The largest image above the fold: high priority. Alt text from the media
             library (current language); empty for decorative images --}}
        <x-responsive-image
            :src="'storage/'.$post->featured_image"
            :priority="true"
            alt="{{ \App\Models\Media::findByPath($post->featured_image)?->altAttribute() ?? '' }}"
            class="mb-10 aspect-[16/9] w-full sm:mb-12 sm:aspect-[21/9] rounded-[var(--site-radius-lg)] border border-[var(--site-border)] object-cover shadow-[var(--site-shadow-lg)]"
        />
    @endif

    <article class="max-w-5xl">
        <div class="site-prose prose prose-lg dark:prose-invert max-w-none">
            {!! \App\Helpers\HtmlHelper::clean($post->content) !!}
        </div>

        @if(($show_prev_next ?? true) && ($prev || $next))
            <nav class="mt-16 grid gap-4 sm:grid-cols-2" aria-label="{{ __('blog::blog.title') }}">
                @if($prev)
                    <a href="{{ route('blog.show', $prev->slug) }}" class="site-card group">
                        <div class="site-card-body !gap-1 !p-5">
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-[var(--site-subtle)]"><x-ui.icon name="arrow-left" /> {{ __('blog::blog.prev_post') }}</span>
                            <span class="font-semibold text-[var(--site-heading)] group-hover:text-[var(--primary-text)]">{{ $prev->title }}</span>
                        </div>
                    </a>
                @else
                    <div class="hidden sm:block"></div>
                @endif
                @if($next)
                    <a href="{{ route('blog.show', $next->slug) }}" class="site-card group text-right">
                        <div class="site-card-body !gap-1 !p-5">
                            <span class="inline-flex items-center justify-end gap-1.5 text-xs font-semibold uppercase tracking-wide text-[var(--site-subtle)]">{{ __('blog::blog.next_post') }} <x-ui.icon name="arrow-right" /></span>
                            <span class="font-semibold text-[var(--site-heading)] group-hover:text-[var(--primary-text)]">{{ $next->title }}</span>
                        </div>
                    </a>
                @endif
            </nav>
        @endif
    </article>
</x-frontend-app-layout>
