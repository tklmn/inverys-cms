/**
 * Focal point of an image in the media library (backend/media/edit).
 *
 * Markup: [data-focal-point] with the image stage, a marker button, the
 * number fields focal_x / focal_y (the values that are saved), a reset
 * button, a live status text and crop previews. Without this script the
 * number fields still work on their own.
 *
 * - Click or drag on the image: the point moves there.
 * - Marker focused: arrow keys move it by 1 %, with Shift by 10 %;
 *   Home / End jump to the left / right edge, PageUp / PageDown to the top / bottom.
 * - Reset: empties both fields (= center, the default).
 */

const clamp = (value) => Math.min(100, Math.max(0, value));
const round = (value) => Math.round(value * 10) / 10;

function readField(input) {
    const value = parseFloat(String(input?.value ?? '').replace(',', '.'));
    return Number.isFinite(value) ? clamp(value) : 50;
}

function init(root) {
    const stage = root.querySelector('[data-focal-stage]');
    const image = root.querySelector('[data-focal-image]');
    const marker = root.querySelector('[data-focal-marker]');
    const inputX = root.querySelector('[data-focal-x]');
    const inputY = root.querySelector('[data-focal-y]');
    const status = root.querySelector('[data-focal-status]');
    const reset = root.querySelector('[data-focal-reset]');
    const previews = root.querySelectorAll('[data-focal-preview]');
    const template = root.dataset.focalText || ':x% / :y%';
    const disabled = root.hasAttribute('data-focal-disabled');

    if (!stage || !image || !marker || !inputX || !inputY) return;

    const render = () => {
        const x = readField(inputX);
        const y = readField(inputY);
        marker.style.left = x + '%';
        marker.style.top = y + '%';
        previews.forEach((preview) => { preview.style.objectPosition = x + '% ' + y + '%'; });
        if (status) {
            status.textContent = template.split(':x').join(String(round(x))).split(':y').join(String(round(y)));
        }
    };

    const set = (x, y) => {
        inputX.value = String(round(clamp(x)));
        inputY.value = String(round(clamp(y)));
        render();
    };

    if (disabled) {
        render();
        return;
    }

    // Pointer: position relative to the image as shown
    const fromPointer = (event) => {
        const box = image.getBoundingClientRect();
        if (box.width === 0 || box.height === 0) return;
        set(((event.clientX - box.left) / box.width) * 100, ((event.clientY - box.top) / box.height) * 100);
    };

    let dragging = false;
    stage.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) return;
        event.preventDefault();
        dragging = true;
        stage.setPointerCapture?.(event.pointerId);
        fromPointer(event);
        marker.focus({ preventScroll: true });
    });
    stage.addEventListener('pointermove', (event) => { if (dragging) fromPointer(event); });
    const stop = () => { dragging = false; };
    stage.addEventListener('pointerup', stop);
    stage.addEventListener('pointercancel', stop);

    marker.addEventListener('keydown', (event) => {
        const step = event.shiftKey ? 10 : 1;
        let x = readField(inputX);
        let y = readField(inputY);

        switch (event.key) {
            case 'ArrowLeft': x -= step; break;
            case 'ArrowRight': x += step; break;
            case 'ArrowUp': y -= step; break;
            case 'ArrowDown': y += step; break;
            case 'Home': x = 0; break;
            case 'End': x = 100; break;
            case 'PageUp': y = 0; break;
            case 'PageDown': y = 100; break;
            default: return;
        }

        event.preventDefault();
        set(x, y);
    });

    // Typing in the fields moves the marker and the previews
    inputX.addEventListener('input', render);
    inputY.addEventListener('input', render);

    reset?.addEventListener('click', () => {
        inputX.value = '';
        inputY.value = '';
        render();
        marker.focus({ preventScroll: true });
    });

    render();
}

document.querySelectorAll('[data-focal-point]').forEach(init);
