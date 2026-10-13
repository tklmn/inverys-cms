/**
 * Dropdown menus from <details data-menu> (e.g. the language switcher in the editor).
 *
 * The browser handles opening and closing itself; here only what <details>
 * cannot do: close on an outside click, on Escape (focus back on the
 * button) and when another menu opens.
 */
const openMenus = () => document.querySelectorAll('details[data-menu][open]');

document.addEventListener('click', (event) => {
    openMenus().forEach((menu) => {
        if (!menu.contains(event.target)) menu.open = false;
    });
});

// A click into an iframe (preview in the page editor) does not reach this
// document — but the window loses focus when it happens
window.addEventListener('blur', () => {
    openMenus().forEach((menu) => { menu.open = false; });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    openMenus().forEach((menu) => {
        menu.open = false;
        menu.querySelector('summary')?.focus();
    });
});

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !menu.matches('[data-menu]') || !menu.open) return;
    openMenus().forEach((other) => {
        if (other !== menu) other.open = false;
    });
}, true);
