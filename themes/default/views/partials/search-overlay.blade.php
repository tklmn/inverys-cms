@if(theme('show_search', true))
{{-- Search as an overlay (like a command palette): Esc or a click outside closes it --}}
<div id="search-overlay" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="search-overlay-heading">
    <div class="absolute inset-0 bg-slate-950/65 backdrop-blur-md" data-csp="search-overlay-close"></div>
    <div class="relative flex min-h-full items-start justify-center px-4 pt-[14vh] sm:pt-[20vh]">
        <form action="{{ route('search') }}" method="GET" role="search" class="w-full max-w-3xl">
            <h2 id="search-overlay-heading" class="mb-5 text-center text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ __('translation.frontend.search_heading') }}</h2>

            <div class="site-search-box">
                <x-ui.icon name="search" />
                <input type="search" name="q" id="search-overlay-input" class="site-search-input"
                       aria-label="{{ __('translation.search') }}" placeholder="{{ __('translation.search') }} …" autocomplete="off">
                <button type="button" data-csp="search-overlay-close" aria-label="{{ __('translation.close') }}" title="{{ __('translation.close') }}" class="site-icon-btn">
                    <x-ui.icon name="x" />
                </button>
                <button type="submit" class="site-btn site-btn-primary site-search-submit">
                    <x-ui.icon name="search" /><span class="site-search-submit-label">{{ __('translation.search') }}</span>
                </button>
            </div>

            <p class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-white/75">
                <span class="inline-flex items-center gap-2"><kbd class="site-kbd">↵</kbd>{{ __('translation.frontend.search_kbd_enter') }}</span>
                <span class="inline-flex items-center gap-2"><kbd class="site-kbd">Esc</kbd>{{ __('translation.frontend.search_kbd_esc') }}</span>{{-- i18n-ignore: key name --}}
                <span class="inline-flex items-center gap-2"><kbd class="site-kbd" data-search-shortcut>{{ __('translation.frontend.search_shortcut') }}</kbd>{{ __('translation.frontend.search_kbd_open') }}</span>
            </p>
        </form>
    </div>
</div>
<script @cspNonce>
function toggleSearchOverlay() {
    var overlay = document.getElementById('search-overlay');
    var input = document.getElementById('search-overlay-input');
    var isHidden = overlay.classList.contains('hidden');
    overlay.classList.toggle('hidden', !isHidden);
    document.body.style.overflow = isHidden ? 'hidden' : '';
    if (isHidden) { input.value = ''; input.focus(); }
}
document.addEventListener('keydown', function (e) {
    var overlay = document.getElementById('search-overlay');
    if (e.key === 'Escape' && !overlay.classList.contains('hidden')) toggleSearchOverlay();

    // Ctrl+K (Mac: Cmd+K) opens the search from anywhere — if it is open, focus jumps to the field
    if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (overlay.classList.contains('hidden')) toggleSearchOverlay();
        else document.getElementById('search-overlay-input').focus();
    }
});

// On the Mac the shortcut is called ⌘K
if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
    document.querySelectorAll('[data-search-shortcut]').forEach(function (el) { el.textContent = '⌘K'; });
    document.querySelectorAll('[aria-keyshortcuts="Control+K"]').forEach(function (el) {
        el.setAttribute('aria-keyshortcuts', 'Meta+K');
        el.title = el.title.replace(el.getAttribute('data-shortcut') || '', '⌘K');
    });
}
document.querySelectorAll('[data-csp="search-overlay-close"]').forEach(function (el) {
    el.addEventListener('click', function () { toggleSearchOverlay(); });
});
</script>
@endif
