{{--
    Pagination in the style of the design system.
    Overrides Laravel's default view so it uses the same tokens as the rest
    of the admin area.
--}}
@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('Pagination Navigation') }}">
        <p class="ui-help">
            {!! __('Showing') !!}
            <span class="font-medium text-fg">{{ $paginator->firstItem() }}</span>
            {!! __('to') !!}
            <span class="font-medium text-fg">{{ $paginator->lastItem() }}</span>
            {!! __('of') !!}
            <span class="font-medium text-fg">{{ $paginator->total() }}</span>
        </p>

        <div class="ui-btn-group">
            @if ($paginator->onFirstPage())
                <span class="ui-btn ui-btn-sm ui-btn-secondary opacity-40" aria-disabled="true">
                    <x-ui.icon name="chevron-left" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="ui-btn ui-btn-sm ui-btn-secondary" aria-label="{{ __('pagination.previous') }}">
                    <x-ui.icon name="chevron-left" />
                </a>
            @endif

            {{-- Narrow: only "3 / 7" between the arrows — with many pages the page
                 numbers ran off the side --}}
            <span class="ui-pages-compact" aria-hidden="true">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            <span class="ui-pages-numbers">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-fg-muted">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="ui-btn ui-btn-sm ui-btn-primary" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="ui-btn ui-btn-sm ui-btn-ghost">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="ui-btn ui-btn-sm ui-btn-secondary" aria-label="{{ __('pagination.next') }}">
                    <x-ui.icon name="chevron-right" />
                </a>
            @else
                <span class="ui-btn ui-btn-sm ui-btn-secondary opacity-40" aria-disabled="true">
                    <x-ui.icon name="chevron-right" />
                </span>
            @endif
        </div>
    </nav>
@endif
