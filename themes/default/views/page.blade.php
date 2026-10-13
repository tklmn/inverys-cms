<x-frontend-app-layout>
    <x-slot name="title">{{ $page->meta_title ?? $page->title }}</x-slot>
    <x-slot name="template">{{ $page->template ?? 'container' }}</x-slot>
    <x-slot name="meta">
        @if ($page->meta_description)
        <meta name="description" content="{{ $page->meta_description }}">
        @endif

        @if ($page->meta_author)
        <meta name="author" content="{{ $page->meta_author }}">
        @endif

        {{-- When indexing is blocked, only the noindex of the SEO settings applies --}}
        @if ($page->meta_robots && \App\Support\SeoSettings::allowIndexing())
        <meta name="robots" content="{{ $page->meta_robots }}">
        @endif

        @if ($page->canonical_url)
        <link rel="canonical" href="{{ $page->canonical_url }}">
        @endif

        <!-- Open Graph tags -->
        @if ($page->og_title)
        <meta property="og:title" content="{{ $page->og_title }}">
        @endif

        @if ($page->og_description)
        <meta property="og:description" content="{{ $page->og_description }}">
        @endif

        @if ($page->og_image)
        <meta property="og:image" content="{{ $page->og_image }}">
        @endif

        @if ($page->extra_css)
        <style>{!! \App\Helpers\HtmlHelper::cleanCss($page->extra_css) !!}</style>
        @endif
    </x-slot>

    @php
        $isFullWidth = ($page->template ?? 'container') === 'full_width';
        $isBlocks = ($page->content_type ?? 'html') === 'blocks' && ! empty($page->blocks);
        $isHome = $page->is_root ?? false;
        // If the page starts with a hero block, it is the stage (with its own H1) — no second page header
        $startsWithHero = $isBlocks && (($page->blocks[0]['type'] ?? '') === 'hero');
        $showBreadcrumbs = theme('show_breadcrumbs', true) && ! $isHome;

        $ancestors = collect();
        if ($showBreadcrumbs) {
            $parent = $page->parent;
            $rootId = $rootPage ? $rootPage->id : null;
            while ($parent) {
                if ($parent->id != $rootId) {
                    $ancestors->prepend($parent);
                }
                $parent = $parent->parent;
            }
        }

        // Subpage list (customizer): designed pages (hero, sections …) usually link their subpages themselves
        $subpagesMode = in_array(theme('show_subpages', 'auto'), ['auto', 'always', 'never'], true) ? theme('show_subpages', 'auto') : 'auto';
        $showSubpages = $subpagesMode === 'always' || ($subpagesMode === 'auto' && ! $page->usesLayoutBlocks());
        $subpages = $showSubpages ? $page->children->where('is_active', true)->where('hide_in_menu', false) : collect();
    @endphp

    {{-- Page header — on the home page the content itself takes the stage --}}
    <x-slot name="header">
        @unless($isHome || $startsWithHero)
            @if($showBreadcrumbs)
                <nav class="site-breadcrumb" aria-label="{{ __('translation.frontend.breadcrumb') }}">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="house" />
                        <span>{{ __('translation.pages.home') }}</span>
                    </a>
                    @foreach($ancestors as $ancestor)
                        <x-ui.icon name="chevron-right" />
                        <a href="{{ $ancestor->is_root ? route('home') : route('page.show', $ancestor->slug) }}">{{ $ancestor->nav_title ?: $ancestor->title }}</a>
                    @endforeach
                    <x-ui.icon name="chevron-right" />
                    <span aria-current="page">{{ $page->nav_title ?: $page->title }}</span>
                </nav>
            @endif

            <h1 class="site-page-title" @if($page->aria_label) aria-label="{{ $page->aria_label }}" @endif>{{ $page->title }}</h1>
            @if($page->subtitle)
                <p class="site-page-lead">{{ $page->subtitle }}</p>
            @endif
        @endunless
    </x-slot>

    @if($page->header_image)
        <x-slot name="headerImage">{{ $page->header_image }}</x-slot>
    @endif

    <article>
        @if($isBlocks)
            <div class="page-blocks {{ $isFullWidth ? '' : 'site-flow' }}">
                {!! app(\App\Services\BlockRenderer::class)->render($page->blocks) !!}
            </div>
        @else
            {{-- Body text at a readable line length --}}
            <div class="{{ $isFullWidth ? 'site-container py-10 sm:py-14' : '' }}">
                <div class="site-prose prose prose-lg dark:prose-invert mx-auto max-w-3xl">
                    {!! \App\Helpers\HtmlHelper::clean($page->content) !!}
                </div>
            </div>
        @endif

        @if($subpages->isNotEmpty())
            <section class="{{ $isFullWidth ? 'site-container pb-16' : '' }} mt-16">
                <h2 class="mb-6 text-2xl font-bold tracking-tight text-[var(--site-heading)]">{{ __('translation.pages.subpages') }}</h2>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($subpages as $childPage)
                        <a href="{{ $childPage->is_root ? route('home') : route('page.show', $childPage->slug) }}" class="site-card group">
                            <div class="site-card-body">
                                <h3 class="site-card-title group-hover:text-[var(--primary-text)]">{{ $childPage->nav_title ?: $childPage->title }}</h3>
                                @if($childPage->meta_description)
                                    <p class="site-card-text line-clamp-3">{{ $childPage->meta_description }}</p>
                                @endif
                                <span class="site-link-arrow">{{ __('Read more') }} <x-ui.icon name="arrow-right" /></span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-frontend-app-layout>
