/**
 * Editor preview (runs INSIDE the page in the iframe, backend.pages.canvas).
 *
 * - Blocks carry data-pb-id (App\Support\EditorCanvas). Hovering shows
 *   an outline with the name, a click selects the block in the editor.
 * - On the selected block: toolbar (up, down, duplicate, delete,
 *   insert after).
 * - Text spots with data-pb-field: a click into a selected block makes them
 *   directly editable; every input goes to the editor (data-pb-rich: HTML,
 *   otherwise plain text). Enter or Esc ends editing.
 * - Links and forms do not leave the preview.
 *
 * Counterpart: resources/js/backend/pages/editor.js. Messages carry
 * source: 'inverys-canvas' or 'inverys-editor' and only apply to the same
 * origin.
 */

const ORIGIN = window.location.origin;
const parentWin = window.parent !== window ? window.parent : null;

function send(type, data = {}) {
    if (parentWin) parentWin.postMessage({ source: 'inverys-canvas', type, ...data }, ORIGIN);
}

document.documentElement.dataset.editorCanvas = '';

// Lucide paths (24px grid) — the preview does not load backend icons
const ICONS = {
    up: '<path d="m18 15-6-6-6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    copy: '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    trash: '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    plus: '<path d="M5 12h14"/><path d="M12 5v14"/>',
    grip: '<circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>',
};
const svg = (name) => `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;

// Fixed colours: the preview shows a foreign theme, backend tokens do not exist here
const style = document.createElement('style');
style.textContent = `
    .pbc-layer { position: absolute; inset: 0 auto auto 0; width: 0; height: 0; z-index: 2147483000; pointer-events: none; }
    .pbc-box { position: absolute; box-sizing: border-box; border-radius: 4px; pointer-events: none; transition: opacity .08s; }
    .pbc-hover { outline: 1px dashed #6366f1; outline-offset: -1px; background: rgba(99, 102, 241, .04); }
    .pbc-selected { outline: 2px solid #4f46e5; outline-offset: -2px; }
    .pbc-label { position: absolute; left: -2px; top: -22px; height: 20px; padding: 0 6px; border-radius: 4px 4px 0 0;
        font: 600 11px/20px system-ui, sans-serif; color: #fff; background: #6366f1; white-space: nowrap; }
    .pbc-selected .pbc-label { background: #4f46e5; }
    .pbc-bar { position: absolute; display: flex; gap: 2px; padding: 3px; border-radius: 8px; pointer-events: auto;
        background: #1e1b4b; box-shadow: 0 6px 20px rgba(15, 23, 42, .25); }
    .pbc-bar button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 0;
        border-radius: 6px; color: #e0e7ff; background: transparent; cursor: pointer; }
    .pbc-bar button:hover, .pbc-bar button:focus-visible { color: #fff; background: rgba(255, 255, 255, .14); outline: none; }
    .pbc-bar button[data-act="delete"]:hover { background: #dc2626; }
    .pbc-bar .pbc-sep { width: 1px; margin: 4px 2px; background: rgba(255, 255, 255, .18); }
    [data-pb-field] { cursor: text; }
    [data-pb-field][contenteditable] { outline: 2px solid #a5b4fc; outline-offset: 2px; border-radius: 2px; cursor: text; }
    [data-pb-id] { cursor: pointer; }
    .pbc-bar button[data-act="drag"] { cursor: grab; touch-action: none; }
    .pbc-drop { position: absolute; height: 3px; margin-top: -1.5px; border-radius: 2px; background: #14B8A6; pointer-events: none;
        box-shadow: 0 0 0 3px rgba(20, 184, 166, .25); }
    .pbc-drop::before, .pbc-drop::after { content: ''; position: absolute; top: -3.5px; width: 10px; height: 10px; border-radius: 50%; background: #14B8A6; }
    .pbc-drop::before { left: -5px; } .pbc-drop::after { right: -5px; }
    html.pbc-dragging, html.pbc-dragging * { cursor: grabbing !important; user-select: none !important; }
    [data-pb-id].pbc-ghost { opacity: .45; }
    [data-pbc-empty] { display: block; min-height: 2.75rem; border-radius: 4px; outline: 1px dashed #a5b4fc; outline-offset: -1px;
        background: rgba(99, 102, 241, .04); }
    [data-pbc-empty]:not([contenteditable])::before { content: attr(data-pbc-empty); display: block; padding: .75rem 1rem;
        font: 400 13px/1.25rem system-ui, sans-serif; color: #6366f1; }
`;
document.head.appendChild(style);

const layer = document.createElement('div');
layer.className = 'pbc-layer';
const hoverBox = document.createElement('div');
hoverBox.className = 'pbc-box pbc-hover';
hoverBox.innerHTML = '<span class="pbc-label"></span>';
const selectBox = document.createElement('div');
selectBox.className = 'pbc-box pbc-selected';
selectBox.innerHTML = '<span class="pbc-label"></span>';
const bar = document.createElement('div');
bar.className = 'pbc-bar';
const dropLine = document.createElement('div');
dropLine.className = 'pbc-drop';
dropLine.style.display = 'none';
layer.append(hoverBox, selectBox, bar, dropLine);

let labels = {};
let texts = {};
let selectedId = null;
let hoverEl = null;
let editing = null;

function renderBar() {
    const btn = (act, icon) => `<button type="button" data-act="${act}" title="${texts[act] || act}" aria-label="${texts[act] || act}">${svg(icon)}</button>`;
    bar.innerHTML = btn('drag', 'grip') + btn('up', 'up') + btn('down', 'down') + '<span class="pbc-sep"></span>'
        + btn('insert', 'plus') + btn('duplicate', 'copy') + btn('delete', 'trash');
}

const blockEl = (id) => (id ? document.querySelector(`[data-pb-id="${CSS.escape(id)}"]`) : null);
const labelOf = (el) => labels[el.dataset.pbId] || el.dataset.pbType || '';

function place(box, el) {
    if (!el) { box.style.display = 'none'; return; }
    const r = el.getBoundingClientRect();
    // Invisible blocks (e.g. "mobile only" at desktop width) have no area
    if (r.width === 0 && r.height === 0) { box.style.display = 'none'; return; }
    box.style.display = '';
    box.style.left = r.left + window.scrollX + 'px';
    box.style.top = r.top + window.scrollY + 'px';
    box.style.width = r.width + 'px';
    box.style.height = r.height + 'px';
    box.querySelector('.pbc-label').textContent = labelOf(el);
    // At the top edge: label goes inside
    box.querySelector('.pbc-label').style.top = r.top + window.scrollY < 24 ? '0' : '-22px';
}

function placeBar(el) {
    if (!el || editing) { bar.style.display = 'none'; return; }
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) { bar.style.display = 'none'; return; }
    bar.style.display = '';
    const width = bar.offsetWidth || 170;
    const top = r.top + window.scrollY;
    bar.style.left = Math.max(4, Math.min(r.right + window.scrollX - width, window.scrollX + document.documentElement.clientWidth - width - 4)) + 'px';
    bar.style.top = (top < 40 ? top + 6 : top - 38) + 'px';
}

function refresh() {
    const sel = blockEl(selectedId);
    place(selectBox, sel);
    placeBar(sel);
    place(hoverBox, hoverEl && hoverEl !== sel ? hoverEl : null);
}

let frame = 0;
function schedule() {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = 0; refresh(); });
}

function select(id, scroll) {
    selectedId = id;
    refresh();
    const el = blockEl(id);
    if (el && scroll) {
        const r = el.getBoundingClientRect();
        if (r.bottom < 0 || r.top > window.innerHeight) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
}

/* ── Inline editing ── */
function startEditing(field) {
    const block = field.closest('[data-pb-id]');
    if (!block) return;
    editing = { field, id: block.dataset.pbId, name: field.dataset.pbField, rich: field.hasAttribute('data-pb-rich') };
    try { field.contentEditable = editing.rich ? 'true' : 'plaintext-only'; } catch (e) { field.contentEditable = 'true'; }
    if (field.contentEditable === 'inherit') field.contentEditable = 'true';
    field.focus();
    bar.style.display = 'none';
    send('edit-start', { id: editing.id });
}

function stopEditing() {
    if (!editing) return;
    const { field, id } = editing;
    field.removeAttribute('contenteditable');
    editing = null;
    send('edit-end', { id });
    schedule();
}

document.addEventListener('input', (e) => {
    if (!editing || e.target !== editing.field) return;
    // Without leading and trailing whitespace: it comes from the template (indentation
    // around the content) and is not part of the text — otherwise it would end up in the data
    const value = editing.rich ? editing.field.innerHTML.trim() : editing.field.innerText.trim();
    send('field', { id: editing.id, field: editing.name, value });
    schedule();
});

document.addEventListener('keydown', (e) => {
    if (!editing) return;
    if (e.key === 'Escape' || (e.key === 'Enter' && !editing.rich && !e.shiftKey)) {
        e.preventDefault();
        editing.field.blur();
    }
});

document.addEventListener('focusout', (e) => {
    if (editing && e.target === editing.field) stopEditing();
});

/* ── Pointer ── */
document.addEventListener('mouseover', (e) => {
    const el = e.target.closest ? e.target.closest('[data-pb-id]') : null;
    if (el === hoverEl) return;
    hoverEl = el;
    schedule();
});

document.addEventListener('mouseleave', () => { hoverEl = null; schedule(); });

document.addEventListener('click', (e) => {
    const act = e.target.closest ? e.target.closest('.pbc-bar [data-act]') : null;
    if (act) {
        e.preventDefault();
        if (act.dataset.act !== 'drag') send('action', { action: act.dataset.act, id: selectedId });
        return;
    }

    const link = e.target.closest ? e.target.closest('a[href]') : null;
    if (link && (link.getAttribute('href') || '').charAt(0) !== '#') e.preventDefault();

    if (editing && editing.field.contains(e.target)) return;

    const block = e.target.closest ? e.target.closest('[data-pb-id]') : null;
    if (!block) return;
    e.preventDefault();

    // Second click into the selected block: edit the text spot
    const field = e.target.closest('[data-pb-field]');
    if (field && field.closest('[data-pb-id]') === block && block.dataset.pbId === selectedId) {
        startEditing(field);
        return;
    }

    select(block.dataset.pbId, false);
    send('select', { id: block.dataset.pbId });
}, true);

// Double click edits right away (even without a prior selection)
document.addEventListener('dblclick', (e) => {
    const field = e.target.closest ? e.target.closest('[data-pb-field]') : null;
    if (!field || editing) return;
    const block = field.closest('[data-pb-id]');
    if (!block) return;
    e.preventDefault();
    select(block.dataset.pbId, false);
    send('select', { id: block.dataset.pbId });
    startEditing(field);
});

document.addEventListener('submit', (e) => e.preventDefault(), true);

/*
 * Dragging the toolbar grip: the target is the block under the pointer,
 * before or after it (upper/lower half). Never into the dragged block
 * itself. The line shows where it lands; the editor moves it (move).
 */
let drag = null;
// Top-level blocks (not inside another block)
const topLevel = () => [...document.querySelectorAll('[data-pb-id]')].filter((el) => !el.parentElement.closest('[data-pb-id]'));

function dropTarget(x, y) {
    const self = blockEl(drag.id);
    let el = document.elementFromPoint(x, y);
    el = el && el.closest ? el.closest('[data-pb-id]') : null;
    while (el && (el === self || self.contains(el))) el = el.parentElement ? el.parentElement.closest('[data-pb-id]') : null;

    // Below the last or above the first block: top level, to the end or the start
    const tops = topLevel().filter((t) => t !== self);
    if (tops.length) {
        const last = tops[tops.length - 1];
        const first = tops[0];
        const lr = last.getBoundingClientRect();
        const fr = first.getBoundingClientRect();
        if (y > lr.bottom - 4) return { el: last, after: true, rect: lr };
        if (y < fr.top + 4) return { el: first, after: false, rect: fr };
    }

    // Over the dragged block itself: no target change
    if (!el || el.contains(self)) return null;
    const r = el.getBoundingClientRect();
    return { el, after: y > r.top + r.height / 2, rect: r };
}

function showDrop(t) {
    if (!t) { dropLine.style.display = 'none'; return; }
    dropLine.style.display = '';
    dropLine.style.left = t.rect.left + window.scrollX + 'px';
    dropLine.style.width = t.rect.width + 'px';
    dropLine.style.top = (t.after ? t.rect.bottom : t.rect.top) + window.scrollY + 'px';
}

document.addEventListener('pointerdown', (e) => {
    const grip = e.target.closest ? e.target.closest('.pbc-bar [data-act="drag"]') : null;
    if (!grip || !selectedId) return;
    e.preventDefault();
    drag = { id: selectedId, target: null };
    blockEl(selectedId)?.classList.add('pbc-ghost');
    document.documentElement.classList.add('pbc-dragging');
    bar.style.display = 'none';
    try { grip.setPointerCapture(e.pointerId); } catch (err) { /* older browsers */ }
});

document.addEventListener('pointermove', (e) => {
    if (!drag) return;
    // Auto-scroll at the edge
    if (e.clientY < 60) window.scrollBy(0, -12);
    else if (e.clientY > window.innerHeight - 60) window.scrollBy(0, 12);
    drag.target = dropTarget(e.clientX, e.clientY);
    showDrop(drag.target);
});

function endDrag(commit) {
    if (!drag) return;
    const { id, target } = drag;
    drag = null;
    blockEl(id)?.classList.remove('pbc-ghost');
    document.documentElement.classList.remove('pbc-dragging');
    showDrop(null);
    if (commit && target) send('move', { id, target: target.el.dataset.pbId, after: target.after });
    schedule();
}

document.addEventListener('pointerup', () => endDrag(true));
document.addEventListener('pointercancel', () => endDrag(false));

/*
 * The editor's keyboard shortcuts also apply when the focus is in the preview
 * — except while typing (there Ctrl+Z stays with the text field).
 */
document.addEventListener('keydown', (e) => {
    if (drag && e.key === 'Escape') { e.preventDefault(); endDrag(false); return; }
    // Ctrl+S also while typing: otherwise the browser opens "Save page as"
    if (editing && (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        send('key', { key: e.key, ctrl: true, shift: e.shiftKey, alt: e.altKey });
        return;
    }
    if (editing || e.defaultPrevented) return;
    const target = e.target;
    if (target && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))) return;
    const mod = e.ctrlKey || e.metaKey;
    const key = e.key.toLowerCase();
    const forward = (mod && ['z', 'y', 'd', 's'].includes(key))
        || (e.altKey && (key === 'arrowup' || key === 'arrowdown'))
        || (!mod && !e.altKey && ['delete', 'backspace', 'escape'].includes(key))
        || e.key === '?';
    if (!forward) return;
    e.preventDefault();
    send('key', { key: e.key, ctrl: mod, shift: e.shiftKey, alt: e.altKey });
});
window.addEventListener('scroll', schedule, { passive: true });
window.addEventListener('resize', schedule);
new ResizeObserver(schedule).observe(document.body);

/*
 * Empty blocks (text block without text, container without content) have no
 * height — they could neither be clicked nor labelled. Once after
 * loading: an area with a hint; typing into it makes it grow.
 */
function markEmpty() {
    for (const el of document.querySelectorAll('[data-pb-id]')) {
        if (el.hasAttribute('data-pbc-empty')) continue;
        const r = el.getBoundingClientRect();
        if (r.height < 4 && getComputedStyle(el).display !== 'none') {
            el.setAttribute('data-pbc-empty', `${labelOf(el)} · ${texts.empty || 'empty'}`);
        }
    }
    schedule();
}

// The hint disappears while typing
document.addEventListener('input', (e) => {
    const empty = e.target.closest ? e.target.closest('[data-pbc-empty]') : null;
    if (empty) empty.removeAttribute('data-pbc-empty');
}, true);

/* ── Messages from the editor ── */
window.addEventListener('message', (e) => {
    if (e.origin !== ORIGIN || e.source !== parentWin || !e.data || e.data.source !== 'inverys-editor') return;
    const msg = e.data;
    if (msg.type === 'init') {
        labels = msg.labels || {};
        texts = msg.texts || {};
        renderBar();
        markEmpty();
        select(msg.selected || null, false);
    } else if (msg.type === 'labels') {
        labels = msg.labels || {};
        schedule();
    } else if (msg.type === 'select') {
        if (editing && editing.id !== msg.id) editing.field.blur();
        select(msg.id || null, true);
    }
});

window.addEventListener('load', () => {
    // On <html>, not <body>: a positioned body would otherwise shift all outlines
    document.documentElement.appendChild(layer);
    renderBar();
    send('ready');
});
