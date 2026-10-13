/**
 * Reset settings to their default.
 *
 * Expects the markup of x-ui.setting-field:
 *   <button data-reset="key" data-default="value">         reset
 *   <input|select data-field="key">                        matching field
 *   #reset-all-btn                                         reset everything
 *
 * The button is only visible while the value differs from the default. Shared
 * by all plugin settings pages.
 */

function fieldFor(key) {
    return document.querySelector(`[data-field="${key}"]`);
}

function buttonFor(key) {
    return document.querySelector(`[data-reset="${key}"]`);
}

function currentValue(field) {
    return field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
}

function sync(field) {
    const button = buttonFor(field.dataset.field);
    if (!button) return;

    button.classList.toggle('hidden', currentValue(field) === button.dataset.default);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-reset]');

    if (button) {
        const field = fieldFor(button.dataset.reset);
        if (!field) return;

        if (field.type === 'checkbox') {
            field.checked = button.dataset.default === '1';
        } else {
            field.value = button.dataset.default;
        }

        field.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    if (event.target.closest('#reset-all-btn')) {
        document.querySelectorAll('[data-reset]').forEach((b) => b.click());
    }
});

['change', 'input'].forEach((type) => {
    document.addEventListener(type, (event) => {
        if (event.target.dataset?.field) sync(event.target);
    });
});
