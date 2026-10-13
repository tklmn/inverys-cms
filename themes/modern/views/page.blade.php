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

    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200"
            @if($page->aria_label) aria-label="{{ $page->aria_label }}" @endif>
            {{ $page->title }}
        </h1>
        @if($page->subtitle)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $page->subtitle }}</p>
        @endif
    </x-slot>

    @if($page->header_image)
        <x-slot name="headerImage">{{ $page->header_image }}</x-slot>
    @endif

    @php
        $isFullWidth = ($page->template ?? 'container') === 'full_width';
        $isBlocks = ($page->content_type ?? 'html') === 'blocks' && !empty($page->blocks);
        $isHome = $page->is_root ?? false;
        $showBreadcrumbs = theme('show_breadcrumbs', true) && !$isHome;

        $ancestors = collect([]);
        if ($showBreadcrumbs) {
            $parent = $page->parent;
            $root = \App\Models\Page::where('is_root', true)->first();
            $rootId = $root ? $root->id : null;
            while ($parent) {
                if ($rootId && $parent->id == $rootId) {
                    $parent = $parent->parent;
                    continue;
                }
                $ancestors->prepend($parent);
                $parent = $parent->parent;
            }
        }
    @endphp

    @if($isFullWidth && $isBlocks)
        {{-- Full width block pages: no wrapper, containers handle everything --}}
        @if($showBreadcrumbs)
            <div class="{{ theme('max_width', 'max-w-5xl') }} mx-auto px-4 sm:px-6 lg:px-8">
                <nav aria-label="{{ __('translation.frontend.breadcrumb') }}">
                    <ol class="flex items-center space-x-2 text-sm">
                        <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"><i class="bi bi-house"></i></a></li>
                        @foreach($ancestors as $ancestor)
                            <li class="flex items-center space-x-2">
                                <i class="bi bi-chevron-right text-gray-300 dark:text-gray-600 text-xs"></i>
                                <a href="{{ $ancestor->is_root ? route('home') : route('page.show', $ancestor->slug) }}" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $ancestor->nav_title ?? $ancestor->title }}</a>
                            </li>
                        @endforeach
                        <li class="flex items-center space-x-2">
                            <i class="bi bi-chevron-right text-gray-300 dark:text-gray-600 text-xs"></i>
                            <span class="text-gray-600 dark:text-gray-300 font-medium">{{ $page->nav_title ?? $page->title }}</span>
                        </li>
                    </ol>
                </nav>
            </div>
        @endif
        <div class="page-blocks">
            {!! app(\App\Services\BlockRenderer::class)->render($page->blocks) !!}
        </div>
    @else
        {{-- Container or HTML pages: wrapped layout --}}
        <div class="py-8">
            @if($showBreadcrumbs)
                <nav aria-label="{{ __('translation.frontend.breadcrumb') }}">
                    <ol class="flex items-center space-x-2 text-sm">
                        <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"><i class="bi bi-house"></i></a></li>
                        @foreach($ancestors as $ancestor)
                            <li class="flex items-center space-x-2">
                                <i class="bi bi-chevron-right text-gray-300 dark:text-gray-600 text-xs"></i>
                                <a href="{{ $ancestor->is_root ? route('home') : route('page.show', $ancestor->slug) }}" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $ancestor->nav_title ?? $ancestor->title }}</a>
                            </li>
                        @endforeach
                        <li class="flex items-center space-x-2">
                            <i class="bi bi-chevron-right text-gray-300 dark:text-gray-600 text-xs"></i>
                            <span class="text-gray-600 dark:text-gray-300 font-medium">{{ $page->nav_title ?? $page->title }}</span>
                        </li>
                    </ol>
                </nav>
            @endif
            <article class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-6 md:p-10">
                @if($isBlocks)
                    <div class="page-blocks">
                        {!! app(\App\Services\BlockRenderer::class)->render($page->blocks) !!}
                    </div>
                @else
                    <div class="prose prose-lg prose-indigo dark:prose-invert max-w-none">
                        {!! \App\Helpers\HtmlHelper::clean($page->content) !!}
                    </div>
                @endif
            </article>

        <!-- Subpages -->
        @if(count($page->children->where('is_active', true)->where('hide_in_menu', false)) > 0)
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">{{ __('translation.pages.subpages') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($page->children->where('is_active', true)->where('hide_in_menu', false) as $childPage)
                        <a href="{{ $childPage->is_root ? route('home') : route('page.show', $childPage->slug) }}" class="modern-card block bg-white dark:bg-gray-900 rounded-2xl p-5 border border-gray-200 dark:border-gray-800">
                            <h3 class="font-bold text-gray-900 dark:text-white mb-1">{{ $childPage->nav_title ?? $childPage->title }}</h3>
                            @if($childPage->meta_description)
                                <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">{{ $childPage->meta_description }}</p>
                            @endif
                            <span class="inline-flex items-center mt-3 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                                {{ __('translation.frontend.read_more') }} <i class="bi bi-arrow-right ml-1"></i>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</x-frontend-app-layout>
