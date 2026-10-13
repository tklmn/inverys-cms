@php
    $items = $results instanceof \Illuminate\Pagination\LengthAwarePaginator ? $results : collect($results);
    $total = $results instanceof \Illuminate\Pagination\LengthAwarePaginator ? $results->total() : $results->count();

    // Highlight the search term in title and description — escape first, then mark
    $highlight = function (?string $text) use ($query): string {
        $safe = e((string) $text);
        $term = trim((string) $query);

        return $term === '' ? $safe : preg_replace('/('.preg_quote(e($term), '/').')/iu', '<mark>$1</mark>', $safe);
    };

    $typeIcons = ['pages' => 'file-text', 'blog' => 'book-open', 'gallery-album' => 'image', 'form' => 'message-square'];
    $pathOf = fn (string $url) => rawurldecode(parse_url($url, PHP_URL_PATH) ?: '/');
@endphp
<x-frontend-app-layout>
    <x-slot name="title">{{ $query ? $query.' – '.__('translation.frontend.search_title') : __('translation.frontend.search_title') }}</x-slot>
    <x-slot name="template">full_width</x-slot>{{-- i18n-ignore: template name --}}

    {{-- Large, centred search field --}}
    <section class="site-search-hero site-surface-glow site-hero-grid">
        <div class="site-container">
            <h1 class="site-search-title">{{ __('translation.frontend.search_heading') }}</h1>
            <p class="site-search-intro">{{ __('translation.frontend.search_intro') }}</p>

            <form action="{{ route('search') }}" method="GET" role="search">
                <div class="site-search-box">
                    <x-ui.icon name="search" />
                    <input type="search" name="q" value="{{ $query }}" class="site-search-input"
                           placeholder="{{ __('translation.search') }} …" aria-label="{{ __('translation.search') }}"
                           autocomplete="off" @unless($query) autofocus @endunless>
                    <button type="submit" class="site-btn site-btn-primary site-search-submit">
                        <x-ui.icon name="search" /><span class="site-search-submit-label">{{ __('translation.search') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <div class="site-container">
        <div class="site-search-results">
            @if($query && $total > 0)
                <p class="site-search-meta" aria-live="polite">
                    <strong>{{ $total }}</strong> {{ __('translation.frontend.search_results_for') }} “{{ $query }}”
                </p>

                <div class="flex flex-col gap-3">
                    @foreach($items as $item)
                        <a href="{{ $item->url }}" class="site-card site-search-hit is-link">
                            <span class="site-icon-badge"><x-ui.icon :name="$typeIcons[$item->type] ?? 'file-text'" /></span>
                            <span class="site-search-hit-body">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="site-card-title">{!! $highlight($item->title) !!}</span>
                                    <span class="site-badge">{{ __("translation.frontend.search_type_{$item->type}") }}</span>
                                </span>
                                @if($item->description)
                                    <span class="site-card-text line-clamp-2">{!! $highlight($item->description) !!}</span>
                                @endif
                                <span class="site-search-hit-path">{{ $pathOf($item->url) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>

                @if($results instanceof \Illuminate\Pagination\LengthAwarePaginator && $results->hasPages())
                    <div class="mt-10">{{ $results->links('frontend.partials.pagination') }}</div>
                @endif
            {{-- Search terms that are too short are not searched — then the hint instead of "no results" --}}
            @elseif($query !== '' && mb_strlen($query) >= $minLength)
                <div class="site-card site-card-center items-center px-6 py-14 text-center">
                    <span class="site-icon-badge mb-3"><x-ui.icon name="search-x" /></span>
                    <p class="site-card-title">{{ __('translation.frontend.search_no_results') }}</p>
                    <p class="site-card-text mt-1">{{ __('translation.frontend.search_no_results_hint') }}</p>
                </div>
            @elseif(request()->has('q'))
                <p class="py-6 text-center text-[var(--site-muted)]">{{ __('translation.frontend.search_min_chars', ['min' => $minLength]) }}</p>
            @endif
        </div>
    </div>
</x-frontend-app-layout>
