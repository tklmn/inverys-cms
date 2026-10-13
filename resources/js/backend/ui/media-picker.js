/**
 * Picking images from the media library.
 *
 * One implementation for every use (page header image, blog featured image,
 * gallery, theme customizer), so they all behave the same.
 *
 * Markup: x-ui.media-picker
 * Trigger:   any element with data-media-picker-open="<picker-id>",
 *            optionally data-media-picker-target="<field-id>" (overrides the target)
 *
 * Single selection: clicking an image writes the value into the target field and closes.
 * Multiple selection (data-multiple): clicks mark items, "Add" confirms.
 * In both cases the dialog fires `media-picker:select` with detail.items.
 */

const DEBOUNCE = 300;

// Selection per dialog, keyed by media ID
const selections = new WeakMap();

function parts(picker) {
    return {
        grid: picker.querySelector('[data-media-picker-grid]'),
        empty: picker.querySelector('[data-media-picker-empty]'),
        loading: picker.querySelector('[data-media-picker-loading]'),
        search: picker.querySelector('[data-media-picker-search]'),
        count: picker.querySelector('[data-media-picker-count]'),
        confirm: picker.querySelector('[data-media-picker-confirm]'),
    };
}

function selection(picker) {
    if (!selections.has(picker)) selections.set(picker, new Map());
    return selections.get(picker);
}

function show(el, visible, display = 'block') {
    if (!el) return;
    el.classList.toggle('hidden', !visible);
    if (visible && display !== 'block') el.classList.add(display);
}

function updateCount(picker) {
    const { count, confirm } = parts(picker);
    const size = selection(picker).size;

    if (count) count.textContent = size ? `${size} ${picker.dataset.selectedLabel || ''}`.trim() : '';
    if (confirm) {
        // x-ui.button also sets aria-disabled when disabling — pointer-events: none
        // hangs on that, so toggle both, otherwise the button stays dead
        confirm.disabled = size === 0;
        confirm.setAttribute('aria-disabled', size === 0 ? 'true' : 'false');
    }
}

function open(picker) {
    picker.classList.remove('hidden');
    picker.classList.add('flex');

    selection(picker).clear();
    updateCount(picker);

    const { search } = parts(picker);
    if (search) search.value = '';

    load(picker, '');
}

function close(picker) {
    picker.classList.add('hidden');
    picker.classList.remove('flex');
}

function emit(picker, items) {
    picker.dispatchEvent(new CustomEvent('media-picker:select', { bubbles: true, detail: { items } }));
}

function choose(picker, item) {
    const target = picker.dataset.target ? document.getElementById(picker.dataset.target) : null;

    if (target) {
        // returns = any field of the API response (path, url, storage_path …)
        target.value = item[picker.dataset.returns || 'path'] ?? item.path;
        target.dispatchEvent(new Event('change', { bubbles: true }));
    }

    emit(picker, [item]);
    close(picker);
}

function toggle(picker, item, button) {
    const chosen = selection(picker);

    if (chosen.has(item.id)) {
        chosen.delete(item.id);
    } else {
        chosen.set(item.id, item);
    }

    button.setAttribute('aria-pressed', chosen.has(item.id) ? 'true' : 'false');
    updateCount(picker);
}

function load(picker, term) {
    const { grid, empty, loading } = parts(picker);
    const multiple = picker.dataset.multiple !== undefined;

    show(loading, true, 'flex');
    show(empty, false);
    show(grid, false);

    const url = new URL(picker.dataset.url, window.location.origin);
    url.searchParams.set('type', picker.dataset.type || 'image');
    if (term) url.searchParams.set('search', term);

    fetch(url, { headers: { Accept: 'application/json' } })
        .then((response) => response.json())
        .then((payload) => {
            show(loading, false);
            grid.replaceChildren();

            const items = payload.data || [];

            if (!items.length) {
                show(empty, true);
                return;
            }

            show(grid, true, 'grid');

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                // aria-pressed carries the selection state; the CSS hangs the
                // border on it, so JS does not have to hand out colour classes.
                button.className = 'ui-pick';

                if (multiple) {
                    button.setAttribute('aria-pressed', selection(picker).has(item.id) ? 'true' : 'false');
                }

                const image = document.createElement('img');
                image.src = item.thumbnail || item.url;
                image.alt = item.name || '';
                image.loading = 'lazy';
                // Show graphics (logos) in full instead of cropped — as in the media list
                if (/\.svg(\?|$)/i.test(image.src)) image.classList.add('is-contain');
                button.appendChild(image);

                // Name at the bottom of the image: without it, similar motifs are hard to tell apart
                if (item.name) {
                    const name = document.createElement('span');
                    name.className = 'ui-pick-name';
                    name.textContent = item.name;
                    button.title = item.name;
                    button.appendChild(name);
                }

                button.addEventListener('click', () => {
                    if (multiple) toggle(picker, item, button);
                    else choose(picker, item);
                });

                grid.appendChild(button);
            });
        })
        .catch(() => {
            show(loading, false);
            show(empty, true);
        });
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-media-picker-open]');

    if (trigger) {
        const picker = document.getElementById(trigger.dataset.mediaPickerOpen);
        if (picker) {
            // One dialog for several fields: the trigger names its own target
            if (trigger.dataset.mediaPickerTarget) picker.dataset.target = trigger.dataset.mediaPickerTarget;
            open(picker);
        }
        return;
    }

    const confirm = event.target.closest('[data-media-picker-confirm]');

    if (confirm) {
        const picker = confirm.closest('[data-media-picker]');
        const items = [...selection(picker).values()];

        if (items.length) {
            emit(picker, items);
            close(picker);
        }
        return;
    }

    const closer = event.target.closest('[data-media-picker-close]');

    if (closer) {
        const picker = closer.closest('[data-media-picker]');
        if (picker) close(picker);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('[data-media-picker]:not(.hidden)').forEach(close);
});

let timer = null;

document.addEventListener('input', (event) => {
    if (!event.target.matches('[data-media-picker-search]')) return;

    const picker = event.target.closest('[data-media-picker]');

    clearTimeout(timer);
    timer = setTimeout(() => load(picker, event.target.value), DEBOUNCE);
});
