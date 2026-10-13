{{--
    Page numbers for the website (search, blog): $items->links('frontend.partials.pagination').
    Styling: .site-pagination in resources/css/frontend/components/site.css.
    Laravel's default view matched neither the colours nor dark mode.
    Page 1 is linked without "?page=1" — one address per page.
--}}
@php
    $firstPageUrl = fn (?string $url) => $url === null ? null : rtrim((string) preg_replace('/([?&])page=1(&|$)/', '$1', $url), '?&');
@endphp
@if ($paginator->hasPages())
    <nav class="site-pagination" aria-label="{{ __('translation.frontend.pagination') }}">
        <p class="site-pagination-info">
            {!! __('translation.frontend.pagination_info', [
                'first' => '<strong>'.e($paginator->firstItem()).'</strong>',
                'last' => '<strong>'.e($paginator->lastItem()).'</strong>',
                'total' => '<strong>'.e($paginator->total()).'</strong>',
            ]) !!}
        </p>

        <ul class="site-pagination-list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="site-page-link is-disabled" aria-disabled="true"><x-ui.icon name="chevron-left" /><span class="sr-only sm:not-sr-only">{{ __('translation.frontend.pagination_previous') }}</span></span>
                @else
                    <a href="{{ $paginator->currentPage() === 2 ? $firstPageUrl($paginator->previousPageUrl()) : $paginator->previousPageUrl() }}" rel="prev" class="site-page-link"><x-ui.icon name="chevron-left" /><span class="sr-only sm:not-sr-only">{{ __('translation.frontend.pagination_previous') }}</span></a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="site-page-gap" aria-hidden="true">{{ $element }}</li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="site-page-link" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $page == 1 ? $firstPageUrl($url) : $url }}" class="site-page-link">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="site-page-link"><span class="sr-only sm:not-sr-only">{{ __('translation.frontend.pagination_next') }}</span><x-ui.icon name="chevron-right" /></a>
                @else
                    <span class="site-page-link is-disabled" aria-disabled="true"><span class="sr-only sm:not-sr-only">{{ __('translation.frontend.pagination_next') }}</span><x-ui.icon name="chevron-right" /></span>
                @endif
            </li>
        </ul>
    </nav>
@endif
