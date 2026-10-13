/**
 * "Copy from German" in the translation editor (x-ui.translation-source).
 *
 *   <button data-translation-copy="/backend/translations/copy?…&signature=…">…</button>
 *
 * Sends the signed URL via POST. A dedicated form does not work:
 * the button often sits inside the editor form, and the browser would close
 * a nested form prematurely.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-translation-copy]');
    if (!button) return;

    event.preventDefault();
    if (button.disabled) return;
    // Only disable briefly (double click) — if the leave-page prompt is
    // cancelled, the button would otherwise stay dead
    button.disabled = true;
    setTimeout(() => { button.disabled = false; }, 3000);

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = button.dataset.translationCopy;
    form.hidden = true;

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    form.append(token);

    document.body.append(form);
    form.submit();
});
