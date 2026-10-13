/**
 * Detail panel of the data lists.
 *
 * A row with data-clickable opens either its link (data-href) or,
 * if it contains a <template data-detail>, the side panel. The content
 * is already rendered server-side — there is no additional request and
 * no permission check in the client.
 */

const SELECTOR_ROW = '.ui-list-row[data-clickable]';
const IGNORE = 'a, button, input, select, textarea, label, [data-no-row-click]'; let lastTrigger = null;

function panel() {
    return document.getElementById('ui-panel');
} function open(row) {
    const el = panel();
    const scrim = document.getElementById('ui-panel-scrim'); const template = row.querySelector('template[data-detail]'); if (!el || !scrim || !template) return;

    const body = document.getElementById('ui-panel-body'); const foot = document.getElementById('ui-panel-foot'); document.getElementById('ui-panel-title').textContent = template.dataset.title || ''; body.replaceChildren(template.content.cloneNode(true));

    // Actions in the detail may stay at the bottom
    const actions = body.querySelector('[data-panel-actions]'); if (actions) {
        foot.replaceChildren(actions);
        foot.classList.remove('hidden'); } else {
        foot.replaceChildren();
        foot.classList.add('hidden'); }

    document.querySelectorAll('.ui-list-row[aria-selected="true"]').forEach((r) => { r.removeAttribute('aria-selected'); });
    row.setAttribute('aria-selected', 'true'); scrim.classList.remove('hidden'); el.classList.remove('hidden'); // Wait one frame so the transition kicks in
    requestAnimationFrame(() => {
        scrim.dataset.open = ''; el.dataset.open = ''; el.focus();
    });

    lastTrigger = row;
}

function close() {
    const el = panel();
    const scrim = document.getElementById('ui-panel-scrim'); if (!el || !scrim || el.classList.contains('hidden')) return; delete el.dataset.open;
    delete scrim.dataset.open;

    const done = () => {
        el.classList.add('hidden'); scrim.classList.add('hidden'); el.removeEventListener('transitionend', done); };

    el.addEventListener('transitionend', done); // Fallback in case transitions are disabled (prefers-reduced-motion)
    setTimeout(done, 300);

    document.querySelectorAll('.ui-list-row[aria-selected="true"]').forEach((r) => { r.removeAttribute('aria-selected'); });

    lastTrigger?.focus?.();
    lastTrigger = null;
}

document.addEventListener('click', (event) => { if (event.target.closest('[data-panel-close]')) { close();
        return;
    }

    const row = event.target.closest(SELECTOR_ROW);
    if (!row) return;

    // Do not intercept clicks on controls inside the row
    if (event.target.closest(IGNORE)) return;

    if (row.querySelector('template[data-detail]')) { open(row);
    } else if (row.dataset.href) {
        window.location.href = row.dataset.href;
    }
});

// Rows are keyboard-reachable and behave like buttons
document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close();
        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ') return; const row = event.target.closest?.(SELECTOR_ROW);
    if (!row || event.target !== row) return;

    event.preventDefault();
    row.click();
});

document.addEventListener('DOMContentLoaded', () => { document.querySelectorAll(SELECTOR_ROW).forEach((row) => {
        if (!row.hasAttribute('tabindex')) row.setAttribute('tabindex', '0'); });
});
