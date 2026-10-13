/**
 * Only one dropdown in the site header is open at a time, in every theme.
 *
 * A dropdown that opens announces itself on the document:
 *     document.dispatchEvent(new CustomEvent('dropdown:open', { detail: element }))
 * and every other open dropdown closes when it hears that, unless the
 * announced element sits inside it (a submenu within "More").
 *
 * Handled here for all themes and plugins:
 * - <details data-dropdown> (e.g. the language menu): announces itself when it
 *   opens; closes when another dropdown opens, on a click outside and on Escape.
 * - [data-dropdown-hover] (submenus that CSS opens on hover and keyboard focus):
 *   announce themselves when the pointer or the focus enters, so a menu that
 *   was opened by click closes.
 * Theme scripts with their own click menus ("More", the mobile menu) dispatch
 * the event when they open and listen for it to close.
 */
export const DROPDOWN_OPEN = 'dropdown:open';

export function announceDropdown(element) {
    document.dispatchEvent(new CustomEvent(DROPDOWN_OPEN, { detail: element }));
}

function openDetails() {
    return document.querySelectorAll('details[data-dropdown][open]');
}

export function initDropdowns() {
    document.querySelectorAll('details[data-dropdown]').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (details.open) announceDropdown(details);
        });
    });

    document.querySelectorAll('[data-dropdown-hover]').forEach((item) => {
        item.addEventListener('pointerenter', () => announceDropdown(item));
        item.addEventListener('focusin', () => announceDropdown(item));
    });

    document.addEventListener(DROPDOWN_OPEN, (event) => {
        const opened = event.detail;
        openDetails().forEach((details) => {
            if (!details.contains(opened)) details.open = false;
        });
        // CSS keeps a submenu open while it holds the focus (:focus-within),
        // e.g. a clicked "More" button: release that focus as well
        const focused = document.activeElement;
        const holder = focused?.closest?.('[data-dropdown-hover]');
        if (holder && !holder.contains(opened) && !opened?.contains?.(holder)) focused.blur();
    });

    document.addEventListener('click', (event) => {
        openDetails().forEach((details) => {
            if (!details.contains(event.target)) details.open = false;
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        openDetails().forEach((details) => {
            const hadFocus = details.contains(document.activeElement);
            details.open = false;
            if (hadFocus) details.querySelector('summary')?.focus();
        });
    });
}
