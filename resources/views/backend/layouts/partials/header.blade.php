<header class="sticky top-0 z-30 flex h-[var(--spacing-header)] flex-none items-center gap-2 border-b border-line bg-surface/55 px-3 backdrop-blur-md sm:px-4">
    <button
        type="button"
        class="ui-btn ui-btn-md ui-btn-icon ui-btn-ghost lg:hidden"
        data-nav-open
        aria-label="{{ __('translation.dashboard.open_sidebar') }}"
    >
        <x-ui.icon name="panel-left" />
    </button>

    {{-- No title when narrow: next to six buttons only "Activ…" would remain — the
         page heading sits directly below anyway --}}
    <div class="min-w-0 flex-1">
        @isset($header)
            <p class="hidden truncate text-sm font-medium text-fg sm:block">{{ $header }}</p>
        @endisset
    </div>

    @include('backend.layouts.partials.header-actions')
</header>
