/**
 * Character counter for fields with a recommended length (meta title, description):
 *
 *   <input data-count-max="60">   → shows "24 / 60" below, yellow once exceeded
 *
 * Only a hint — longer values are saved too.
 */

document.querySelectorAll('[data-count-max]').forEach((field) => {
    const max = parseInt(field.dataset.countMax, 10);
    if (!max) return;

    const counter = document.createElement('span');
    counter.className = 'ui-count';
    counter.setAttribute('aria-live', 'polite');
    field.insertAdjacentElement('afterend', counter);

    const update = () => {
        const length = field.value.length;
        counter.textContent = `${length} / ${max}`;
        counter.classList.toggle('is-over', length > max);
    };

    field.addEventListener('input', update);
    update();
});
