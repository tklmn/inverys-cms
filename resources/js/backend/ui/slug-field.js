/**
 * Slug field (pages, posts …):
 *
 *   <div data-slug-field data-slug-source="title" [data-slug-auto]>
 *       <input data-slug-input readonly>  <input type="hidden" data-slug-flag>
 *       <button data-slug-edit>  <button data-slug-generate hidden>
 *   </div>
 *
 * `data-slug-auto` (only when creating): the slug follows the title until it is
 * edited by hand. When editing it stays fixed — otherwise a new title would
 * silently change the address of a published page.
 */

export function slugify(text) {
    return String(text)
        .toLowerCase()
        .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss') // german-ok: transliterates German titles
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

document.querySelectorAll('[data-slug-field]').forEach((field) => {
    const input = field.querySelector('[data-slug-input]');
    const flag = field.querySelector('[data-slug-flag]');
    const edit = field.querySelector('[data-slug-edit]');
    const generate = field.querySelector('[data-slug-generate]');
    const source = document.getElementById(field.dataset.slugSource || 'title');
    if (!input) return;

    const setManual = (manual) => {
        input.readOnly = !manual;
        if (flag) flag.value = manual ? '1' : '0';
        if (edit) edit.hidden = manual;
        if (generate) generate.hidden = !manual;
        if (manual) input.focus();
    };

    if (field.hasAttribute('data-slug-auto') && source) {
        source.addEventListener('input', () => {
            if (flag?.value !== '1') input.value = slugify(source.value);
        });
    }

    edit?.addEventListener('click', () => setManual(true));
    generate?.addEventListener('click', () => {
        if (source) input.value = slugify(source.value);
        setManual(false);
    });
});
