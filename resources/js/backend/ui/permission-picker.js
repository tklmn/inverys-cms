/**
 * Selection helpers in a role's permission picker.
 *
 * "All" and "None" only affect the currently visible checkboxes — after
 * filtering, nobody wants to toggle the hidden ones by accident.
 * Counters: total in the toolbar, per group in the group header.
 */

function picker(el) {
    return el.closest('[data-permission-picker]');
}

function visibleBoxes(root) {
    return [...root.querySelectorAll('.permission-checkbox')].filter(
        (box) => !box.closest('[data-filter-item]')?.classList.contains('hidden'),
    );
}

function updateCounts(root) {
    const counter = root.querySelector('[data-selected-count]');
    if (counter) {
        counter.textContent = root.querySelectorAll('.permission-checkbox:checked').length;
    }

    root.querySelectorAll('[data-filter-group]').forEach((group) => {
        const groupCounter = group.querySelector('[data-group-count]');
        if (groupCounter) {
            groupCounter.textContent = group.querySelectorAll('.permission-checkbox:checked').length;
        }
    });
}

document.addEventListener('click', (event) => {
    const root = picker(event.target);
    if (!root) return;

    if (event.target.closest('[data-select-all]')) {
        visibleBoxes(root).forEach((box) => { box.checked = true; });
        updateCounts(root);
        return;
    }

    if (event.target.closest('[data-deselect-all]')) {
        visibleBoxes(root).forEach((box) => { box.checked = false; });
        updateCounts(root);
        return;
    }

    const groupButton = event.target.closest('[data-select-group]');
    if (groupButton) {
        const group = groupButton.closest('[data-filter-group]');
        const boxes = [...group.querySelectorAll('.permission-checkbox')];
        // The same button toggles the group: all on, otherwise all off
        const allChecked = boxes.every((box) => box.checked);
        boxes.forEach((box) => { box.checked = !allChecked; });
        updateCounts(root);
    }
});

document.addEventListener('change', (event) => {
    if (!event.target.matches('.permission-checkbox')) return;
    const root = picker(event.target);
    if (root) updateCounts(root);
});
