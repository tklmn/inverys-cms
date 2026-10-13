<x-frontend-app-layout>
    <x-slot name="title">{{ $page->meta_title ?? $page->title }}</x-slot>
    <x-slot name="template">{{ $page->template ?? 'container' }}</x-slot>
    <x-slot name="meta">
        {{-- Description, robots, canonical, Open Graph and JSON-LD come from the core (SEO head hook) --}}
        @if ($page->extra_css)
        <style>{!! \App\Helpers\HtmlHelper::cleanCss($page->extra_css) !!}</style>
        @endif
    </x-slot>

    <x-slot name="header">
        {{-- No aria-label: it would replace the visible title (WCAG 2.5.3) --}}
        <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
            {{ $page->title }}
        </h1>
        @if($page->subtitle)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $page->subtitle }}</p>
        @endif
    </x-slot>

    @if($page->header_image)
        <x-slot name="headerImage">{{ $page->header_image }}</x-slot>
    @endif

    {{-- The page's "ARIA label": names the main region, never the heading --}}
    @if($page->aria_label)
        <x-slot name="mainLabel">{{ $page->aria_label }}</x-slot>
    @endif

    @php
        $isFullWidth = ($page->template ?? 'container') === 'full_width';
        $isBlocks = ($page->content_type ?? 'html') === 'blocks' && !empty($page->blocks);
        $isHome = $page->is_root ?? false;
        $showBreadcrumbs = theme('show_breadcrumbs', true) && !$isHome;

        $ancestors = collect([]);
        if ($showBreadcrumbs) {
            $parent = $page->parent;
            $rootId = $rootPage ? $rootPage->id : null;
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

    <div class="{{ $isFullWidth ? '' : 'container mx-auto px-4 py-8' }}">
        @if($showBreadcrumbs)
            <div class="{{ $isFullWidth ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4' : '' }}">
                <nav class="text-sm text-gray-500 dark:text-gray-400" aria-label="{{ __('translation.frontend.breadcrumb') }}">
                    <ol class="list-none p-0 inline-flex">
                        <li class="flex items-center">
                            <a href="{{ route('home') }}" class="inline-flex items-center hover:underline">
                                <svg class="w-4 h-4 mr-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7A1 1 0 003 11h1v6a1 1 0 001 1h3a1 1 0 001-1v-3h2v3a1 1 0 001 1h3a1 1 0 001-1v-6h1a1 1 0 00.707-1.707l-7-7z"/></svg>
                                <span>{{ __('translation.pages.home') }}</span>
                            </a>
                        </li>
                        @foreach($ancestors as $ancestor)
                            <li class="flex items-center">
                                <svg class="fill-current w-3 h-3 mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" aria-hidden="true"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                                <a href="{{ $ancestor->is_root ? route('home') : route('page.show', $ancestor->slug) }}" class="hover:underline">{{ $ancestor->nav_title ?? $ancestor->title }}</a>
                            </li>
                        @endforeach
                        <li class="flex items-center">
                            <svg class="fill-current w-3 h-3 mx-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" aria-hidden="true"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                            <span>{{ $page->nav_title ?? $page->title }}</span>
                        </li>
                    </ol>
                </nav>
            </div>
        @endif

        <article>
            @if($isBlocks)
                <div class="page-blocks">
                    {{-- A header image above the blocks takes the image priority --}}
                    {!! app(\App\Services\BlockRenderer::class)->render($page->blocks, prioritizeFirstImage: ! $page->header_image) !!}
                </div>
            @else
                <div class="prose prose-lg dark:prose-invert max-w-none">
                    {!! \App\Helpers\HtmlHelper::clean($page->content) !!}
                </div>
            @endif

            {{-- As in the default theme: designed pages usually link their subpages themselves --}}
            @php($subpagesMode = in_array(theme('show_subpages', 'auto'), ['auto', 'always', 'never'], true) ? theme('show_subpages', 'auto') : 'auto')
            @if(($subpagesMode === 'always' || ($subpagesMode === 'auto' && ! $page->usesLayoutBlocks())) && count($page->children->where('is_active', true)->where('hide_in_menu', false)) > 0)
                <div class="mt-12 border-t border-gray-200 dark:border-gray-700 pt-8">
                    <h2 class="text-2xl font-bold mb-6">{{ __('translation.pages.subpages') }}</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($page->children->where('is_active', true)->where('hide_in_menu', false) as $childPage)
                            <a href="{{ $childPage->is_root ? route('home') : route('page.show', $childPage->slug) }}" class="group">
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 transition-all duration-200 hover:shadow-md hover:border-blue-300 dark:hover:border-blue-500">
                                    <h3 class="text-xl font-semibold mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                        {{ $childPage->nav_title ?? $childPage->title }}
                                    </h3>
                                    @if($childPage->meta_description)
                                        <p class="text-gray-600 dark:text-gray-400 line-clamp-3">
                                            {{ $childPage->meta_description }}
                                        </p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    </div>
</x-frontend-app-layout>
