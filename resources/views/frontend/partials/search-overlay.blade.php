@if(theme('show_search', true))
<div id="search-overlay" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-label="{{ __('translation.search') }}">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-csp="search-overlay-close"></div>
    <div class="relative flex items-start justify-center pt-24 sm:pt-32">
        <form action="{{ route('search') }}" method="GET" class="w-full max-w-2xl mx-4" aria-labelledby="search-overlay-heading">
            <h2 id="search-overlay-heading" class="sr-only">{{ __('translation.search') }}</h2>
            <div class="relative">
                <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl"></i>
                <input type="text" name="q" id="search-overlay-input"
                       aria-label="{{ __('translation.search') }}"
                       placeholder="{{ __('translation.search') }}..."
                       class="w-full pl-12 pr-12 py-4 text-lg rounded-2xl border-0 shadow-2xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                       autocomplete="off">
                <button type="button" data-csp="search-overlay-close" aria-label="{{ __('translation.close') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            <p class="text-center text-sm text-white/70 mt-3">{{ __('translation.frontend.search_hint') }}</p>
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
    if (isHidden) { input.focus(); input.value = ''; }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var overlay = document.getElementById('search-overlay');
        if (!overlay.classList.contains('hidden')) toggleSearchOverlay();
    }
});
document.querySelectorAll('[data-csp="search-overlay-close"]').forEach(function(el) {
    el.addEventListener('click', function() { toggleSearchOverlay(); });
});
</script>
@endif
