/**
 * Links the color picker and hex input of x-ui.color.
 *
 * Both directions: picked in the color picker -> text follows, hex typed -> picker
 * follows (once the value is complete). Delegated on the document so that
 * fields loaded later work too.
 */

const HEX = /^#[0-9a-f]{6}$/i;

// input: while dragging in the picker / typing; change: also on reset
// via setting-reset.js, which sets the value and fires `change`
const onChange = (event) => {
    const wrap = event.target.closest('[data-color-field]');
    if (!wrap) return;

    const source = wrap.querySelector('[data-color-source]');
    const mirror = wrap.querySelector('[data-color-mirror]');
    if (!source || !mirror) return;

    if (event.target === source) {
        mirror.value = source.value;
        return;
    }

    if (event.target === mirror && HEX.test(mirror.value.trim())) {
        source.value = mirror.value.trim();
        // Other modules (preview, reset button) listen to the color picker
        source.dispatchEvent(new Event('change', { bubbles: true }));
    }
};

document.addEventListener('input', onChange);
document.addEventListener('change', onChange);
