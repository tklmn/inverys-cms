/**
 * Instant filter for data lists.
 *
 * An input with data-filter-input filters all rows with
 * data-filter-item inside the matching data-filter-scope. Group headers
 * disappear too when their group becomes empty. This is deliberately client-side only:
 * the lists are fully rendered, a request per keystroke would be
 * wasteful.
 */

function scopeOf(input) {
    return input.closest('.ui-card, form, body')?.querySelector('[data-filter-scope]') ?? null;
} function apply(input) {
    const scope = scopeOf(input);
    if (!scope) return;

    const term = input.value.trim().toLowerCase();
    const items = scope.querySelectorAll('[data-filter-item]'); let visible = 0;

    items.forEach((item) => {
        const match = term === '' || item.dataset.filterItem.includes(term); item.classList.toggle('hidden', !match); if (match) visible++;
    });

    // Only show a group header if something is still visible below it.
    // Two layouts: header as a sibling of the rows (lists) or as a
    // wrapper around the whole group (permission picker).
    scope.querySelectorAll('[data-filter-group]').forEach((group) => { const inside = group.querySelectorAll('[data-filter-item]'); let hasVisible = false;

        if (inside.length) {
            hasVisible = [...inside].some((el) => !el.classList.contains('hidden')); } else {
            for (let el = group.nextElementSibling; el; el = el.nextElementSibling) {
                if (el.hasAttribute('data-filter-group')) break; if (el.hasAttribute('data-filter-item') && !el.classList.contains('hidden')) { hasVisible = true;
                    break;
                }
            }
        }

        group.classList.toggle('hidden', !hasVisible); });

    const empty = scope.querySelector('[data-filter-empty]'); if (empty) empty.classList.toggle('hidden', visible > 0); const counter = document.querySelector('[data-filter-count]'); if (counter) {
        if (counter.dataset.total === undefined) counter.dataset.total = counter.textContent.trim();
        counter.textContent = term === '' ? counter.dataset.total
            : `${visible} / ${items.length}`;
    }
}

document.addEventListener('input', (event) => { if (event.target.matches('[data-filter-input]')) apply(event.target);
}); // Escape clears the field without leaving the page
document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && event.target.matches('[data-filter-input]')) { event.stopPropagation();
        event.target.value = ''; apply(event.target);
    }
});
