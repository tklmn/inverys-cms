/**
 * CSP-safe delegated event handlers.
 *
 * Takes the place of inline `on*="…"` attributes, which the nonce-based
 * Content-Security-Policy does not allow (inline event handlers cannot carry a
 * nonce): the same events are dispatched / the same forms submitted from a
 * delegated listener. One-off handlers live in their view in a nonced
 * <script> instead. See docs/PLUGIN-DEVELOPMENT.md / THEME-DEVELOPMENT.md.
 *
 * Markup contract:
 *   data-delete-modal='<json>'  → window CustomEvent('open-delete-modal', {detail: json})
 *   data-open-modal='<string>'  → window CustomEvent('open-modal', {detail: string, bubbles:true})
 *   data-close-modal            → element CustomEvent('close', {bubbles:true})
 *   data-autosubmit  (on change)→ element.form.submit()
 *   data-confirm='<msg>' (form) → window.uiConfirm (backend dialog) or window.confirm
 *       optional data-confirm-title, data-confirm-label, data-confirm-tone="danger"
 */

function parseDetail(raw) {
    try {
        return JSON.parse(raw);
    } catch (e) {
        return {};
    }
}

document.addEventListener('click', (e) => {
    const del = e.target.closest('[data-delete-modal]');
    if (del) {
        window.dispatchEvent(new CustomEvent('open-delete-modal', { detail: parseDetail(del.getAttribute('data-delete-modal')) }));
        return;
    }

    const open = e.target.closest('[data-open-modal]');
    if (open) {
        window.dispatchEvent(new CustomEvent('open-modal', { detail: open.getAttribute('data-open-modal'), bubbles: true }));
        return;
    }

    const close = e.target.closest('[data-close-modal]');
    if (close) {
        close.dispatchEvent(new CustomEvent('close', { bubbles: true }));
    }
});

document.addEventListener('change', (e) => {
    const el = e.target.closest('[data-autosubmit]');
    if (el && el.form) {
        el.form.submit();
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.matches || !form.matches('[data-confirm]')) {
        return;
    }
    // Already confirmed (second pass after the dialog)
    if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
    }

    const message = form.getAttribute('data-confirm');
    if (typeof window.uiConfirm !== 'function') {
        if (!window.confirm(message)) {
            e.preventDefault();
        }
        return;
    }

    // Backend-style dialog: stop first, resubmit after "OK"
    e.preventDefault();
    e.stopImmediatePropagation();
    const submitter = e.submitter && e.submitter.form === form ? e.submitter : undefined;
    window.uiConfirm({
        text: message,
        title: form.getAttribute('data-confirm-title') || undefined,
        confirm: form.getAttribute('data-confirm-label') || undefined,
        tone: form.getAttribute('data-confirm-tone') || undefined,
    }).then((ok) => {
        if (!ok) {
            return;
        }
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
        } else {
            form.submit();
        }
    });
}, true);
