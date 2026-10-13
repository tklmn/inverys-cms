{{-- Global media picker for the TinyMCE integration --}}
{{-- Reacts to the toolbar button's 'tinymce-media-request' event --}}
<div id="media-picker-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="media-picker-title"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
    style="display:none"
>
    <div id="media-picker-backdrop" class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm"></div>

    <div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col rounded-xl border border-line bg-surface shadow-[var(--shadow-popover)]">
        <div class="ui-card-head">
            <h2 id="media-picker-title" class="ui-card-title">{{ __('translation.media.title') }}</h2>
            <button id="media-picker-close" aria-label="{{ __('translation.close') }}" class="ui-btn ui-btn-sm ui-btn-icon ui-btn-ghost">
                <x-ui.icon name="x" />
            </button>
        </div>

        <x-ui.toolbar>
            <x-ui.input
                icon="search"
                type="search"
                id="media-picker-search"
                class="w-full"
                :aria-label="__('translation.search_placeholder')"
                :placeholder="__('translation.search_placeholder')"
            />
        </x-ui.toolbar>

        <div class="flex-1 overflow-y-auto p-4">
            <div id="media-picker-loading" class="flex items-center justify-center py-12" style="display:none!important">
                <x-ui.icon name="loader-circle" class="animate-spin text-2xl text-fg-subtle" />
            </div>

            <div id="media-picker-empty" style="display:none!important">
                <x-ui.empty icon="image">{{ __('translation.media.no_files') }}</x-ui.empty>
            </div>

            <div id="media-picker-grid"
                 class="grid grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-2"
                 style="display:none!important">
            </div>
        </div>

        <div id="media-picker-pagination" class="ui-card-foot flex items-center justify-between" style="display:none!important">
            <button id="media-picker-prev" class="ui-btn ui-btn-sm ui-btn-secondary disabled:opacity-40">
                <x-ui.icon name="chevron-left" /> {{ __('pagination.previous') }}
            </button>
            <span id="media-picker-page-info" class="ui-help"></span>
            <button id="media-picker-next" class="ui-btn ui-btn-sm ui-btn-secondary disabled:opacity-40">
                {{ __('pagination.next') }} <x-ui.icon name="chevron-right" />
            </button>
        </div>
    </div>
</div>

<script @cspNonce>
(function () {
    var modal        = document.getElementById('media-picker-modal');
    var backdrop     = document.getElementById('media-picker-backdrop');
    var closeBtn     = document.getElementById('media-picker-close');
    var searchInput  = document.getElementById('media-picker-search');
    var loadingEl    = document.getElementById('media-picker-loading');
    var emptyEl      = document.getElementById('media-picker-empty');
    var gridEl       = document.getElementById('media-picker-grid');
    var paginationEl = document.getElementById('media-picker-pagination');
    var prevBtn      = document.getElementById('media-picker-prev');
    var nextBtn      = document.getElementById('media-picker-next');
    var pageInfo     = document.getElementById('media-picker-page-info');

    var state = {
        open:      false,
        items:     [],
        search:    '',
        loading:   false,
        callback:  null,
        editorId:  null,
        page:      1,
        lastPage:  1,
        debounce:  null
    };

    function show(el)  { el.style.removeProperty('display'); }
    function hide(el)  { el.style.setProperty('display', 'none', 'important'); }

    function openModal() {
        state.open = true;
        modal.style.display = 'flex';
    }

    function closeModal() {
        state.open     = false;
        state.callback = null;
        state.editorId = null;
        modal.style.display = 'none';
    }

    function renderState() {
        if (state.loading) {
            show(loadingEl); hide(emptyEl); hide(gridEl);
        } else if (state.items.length === 0) {
            hide(loadingEl); show(emptyEl); hide(gridEl);
        } else {
            hide(loadingEl); hide(emptyEl); show(gridEl);
        }

        if (state.lastPage > 1) {
            show(paginationEl);
        } else {
            hide(paginationEl);
        }

        pageInfo.textContent = state.page + ' / ' + state.lastPage;
        prevBtn.disabled = state.page <= 1;
        nextBtn.disabled = state.page >= state.lastPage;
    }

    function renderGrid() {
        gridEl.innerHTML = '';
        state.items.forEach(function (item) {
            // The same tile as in the media dialog (ui-pick, form.css)
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ui-pick';
            btn.title = item.name || '';

            var img = document.createElement('img');
            img.src = item.thumbnail || item.url;
            img.alt = item.alt || item.name;
            img.loading = 'lazy';
            if (/[.]svg([?]|$)/i.test(img.src)) img.className = 'is-contain';
            btn.appendChild(img);

            if (item.name) {
                var name = document.createElement('span');
                name.className = 'ui-pick-name';
                name.textContent = item.name;
                btn.appendChild(name);
            }

            btn.addEventListener('click', function () {
                if (state.callback) {
                    state.callback(item.storage_path, { alt: item.alt || item.name });
                }
                closeModal();
            });

            gridEl.appendChild(btn);
        });
    }

    async function fetchMedia() {
        state.loading = true;
        renderState();
        try {
            var params = new URLSearchParams({ type: 'image', page: state.page });
            if (state.search) params.set('search', state.search);
            var res  = await fetch('{{ route("backend.media.api.list") }}?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var json = await res.json();
            state.items    = json.data || [];
            state.lastPage = (json.meta && json.meta.last_page) ? json.meta.last_page : 1;
        } catch (err) {
            console.error('Media fetch error', err);
            state.items = [];
        }
        state.loading = false;
        renderGrid();
        renderState();
    }

    // Listen for TinyMCE media requests
    document.addEventListener('tinymce-media-request', function (e) {
        state.callback = e.detail.callback;
        state.editorId = e.detail.editorId;
        state.search   = '';
        state.page     = 1;
        searchInput.value = '';
        openModal();
        fetchMedia();
    });

    // Close handlers
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && state.open) closeModal();
    });

    // Search with debounce
    searchInput.addEventListener('input', function () {
        state.search = searchInput.value;
        clearTimeout(state.debounce);
        state.debounce = setTimeout(function () {
            state.page = 1;
            fetchMedia();
        }, 300);
    });

    // Pagination
    prevBtn.addEventListener('click', function () {
        if (state.page > 1) { state.page--; fetchMedia(); }
    });
    nextBtn.addEventListener('click', function () {
        if (state.page < state.lastPage) { state.page++; fetchMedia(); }
    });
})();
</script>
