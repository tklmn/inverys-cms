{{--
    Language menu in the theme navigation (navigation hook).
    No JavaScript: <details> opens and closes. Only visible when more than
    one language is installed. Colours from the site tokens (--site-*,
    --primary) so it blends into any theme.
--}}
@php($locales = app(\App\Support\Locales::class))

@if($locales->isMultilingual())
    @php($alternates = \Plugins\Multilingual\Support\Alternates::all())

    <details class="ml-switcher relative">
        {{-- No aria-label: the name is the text itself, "Choose language: EN" —
             the visible text must be contained in it (WCAG 2.5.3) --}}
        <summary class="site-icon-btn cursor-pointer list-none [&::-webkit-details-marker]:hidden" title="{{ __('multilingual::ml.choose_language') }}">
            <x-ui.icon name="languages" />
            <span class="sr-only">{{ __('multilingual::ml.choose_language') }}:</span>
            <span class="uppercase">{{ $locales->visitor() ?? $locales->default() }}</span>
        </summary>

        <ul class="absolute right-0 z-50 mt-2 min-w-44 overflow-hidden rounded-2xl border border-[var(--site-border)] bg-[var(--site-surface-raised)] p-1.5 shadow-[var(--site-shadow-lg)]">
            @foreach($alternates as $code => $alt)
                <li>
                    <a
                        href="{{ $alt['url'] }}"
                        hreflang="{{ $code }}"
                        lang="{{ $code }}"
                        @if($alt['current']) aria-current="true" @endif
                        class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 text-sm {{ $alt['current'] ? 'bg-[var(--primary-soft)] font-semibold text-[var(--primary-text)]' : 'text-[var(--site-text)] hover:bg-[var(--site-bg-soft)]' }}"
                    >
                        {{ $alt['native'] }}
                        <span class="text-xs uppercase text-[var(--site-subtle)]">{{ $code }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
