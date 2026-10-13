/**
 * Gray out dependent settings while their master switch is off.
 *
 *   <x-ui.switch-row name="two_factor_enabled" … />
 *   <div data-requires="two_factor_enabled"> … fields … </div>
 *
 *   data-requires="name"          active if the switch "name" is on
 *   data-requires="name=value"    active if the field "name" has the value (select)
 *   data-requires="!name"         active if the switch is off
 *   data-requires="!name=value"   active if the field has a different value
 *
 * "value" also applies to option cards (radio groups): the selected option
 * is compared.
 *
 * Grayed out means `inert` (not operable, not focusable) — but not
 * `disabled`: the browser does not submit disabled fields, and the server
 * would lose the stored values. Follows the principle "locked rather than
 * hidden": the fields stay visible.
 */

function fieldsNamed(scope, name) {
    // A form also owns the fields placed outside of it via form="id" (e.g. option
    // cards in a side column) — form.elements includes them, querySelectorAll does not
    if (scope instanceof HTMLFormElement) {
        return [...scope.elements].filter((field) => field.name === name && field.type !== 'hidden');
    }

    return [...scope.querySelectorAll(`[name="${CSS.escape(name)}"]:not([type="hidden"])`)];
}

function masterFor(scope, name) {
    // The switch has a hidden off field with the same name — take the real element
    const fields = fieldsNamed(scope, name);

    // Option cards (x-ui.option-card): value of the selected option, empty without a selection
    if (fields.length && fields[0].type === 'radio') {
        return [...fields].find((field) => field.checked) ?? { type: 'radio', value: '' };
    }

    return fields[0] ?? null;
}

function isActive(scope, rule) {
    const negate = rule.startsWith('!');
    const [name, expected] = rule.replace(/^!/, '').split('=');
    const master = masterFor(scope, name);
    if (!master) return true;

    const active = expected !== undefined
        ? master.value === expected
        : (master.type === 'checkbox' ? master.checked : master.value !== '' && master.value !== '0' && master.value !== 'off');

    return negate ? !active : active;
}

function sync(el) {
    const scope = el.closest('form') || document;
    const active = isActive(scope, el.dataset.requires);
    el.inert = !active;
    el.classList.toggle('ui-inactive', !active);
}

function syncAll() {
    document.querySelectorAll('[data-requires]').forEach(sync);
}

document.addEventListener('change', (event) => {
    if (event.target.name) syncAll();
});
document.addEventListener('DOMContentLoaded', syncAll);
