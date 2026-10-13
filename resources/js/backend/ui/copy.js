/**
 * Copy to clipboard.
 *
 *   <code id="new-token">…</code>
 *   <button data-copy="#new-token">…</button>
 *
 * Copies the target's text (the value for input fields) and confirms
 * with a toast. Without the Clipboard API (http without localhost) the
 * text is selected so it can be copied with Ctrl+C.
 */
import { t } from './i18n.js';

function select(el) {
    const range = document.createRange();
    range.selectNodeContents(el);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    const target = document.querySelector(button.dataset.copy);
    if (!target) return;

    const text = ('value' in target ? target.value : target.textContent).trim();

    try {
        await navigator.clipboard.writeText(text);
        window.uiToast?.(t('copied', 'Copied to clipboard.'));
    } catch {
        select(target);
    }
});
