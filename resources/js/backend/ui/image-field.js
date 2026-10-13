/**
 * Image fields (x-ui.image-field): keep the preview up to date, handle "Remove".
 *
 * The preview reacts to input and change — both are fired by typing,
 * the media library (media-picker.js) and reset (setting-reset.js).
 */

const base = (document.querySelector('meta[name="asset-url"]')?.content || '/').replace(/\/?$/, '/');

function refresh(input) {
    const field = input.closest('[data-image-field]');
    if (!field) return;

    const value = input.value.trim();
    const img = field.querySelector('[data-image-preview]');
    const box = img?.parentElement;

    if (img) {
        img.hidden = !value;
        if (value) img.src = /^https?:/.test(value) ? value : base + value.replace(/^\//, '');
    }
    box?.classList.toggle('is-empty', !value);
}

['input', 'change'].forEach((type) => {
    document.addEventListener(type, (event) => {
        if (event.target.matches?.('[data-image-input]')) refresh(event.target);
    });
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-image-clear]');
    if (!button) return;

    const input = button.closest('[data-image-field]')?.querySelector('[data-image-input]');
    if (!input) return;

    input.value = '';
    input.dispatchEvent(new Event('change', { bubbles: true }));
});
