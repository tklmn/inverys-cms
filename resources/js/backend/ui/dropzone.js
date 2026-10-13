/**
 * Drop zone for files.
 *
 * Shows the selected file name and accepts files via drag & drop.
 * The actual input field stays invisible but keeps
 * focus and keyboard operation.
 */

function setFile(zone, name) {
    const out = zone.querySelector('[data-dropzone-file]');
    if (!out) return;

    out.textContent = name || '';
    out.classList.toggle('hidden', !name);
}

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-dropzone-input]')) return;

    const zone = event.target.closest('[data-dropzone]');
    const files = event.target.files;
    setFile(zone, files && files.length ? files[0].name : '');
});

['dragover', 'dragleave', 'drop'].forEach((type) => {
    document.addEventListener(type, (event) => {
        const zone = event.target.closest?.('[data-dropzone]');
        if (!zone) return;

        event.preventDefault();
        zone.toggleAttribute('data-over', type === 'dragover');

        if (type !== 'drop') return;

        const files = event.dataTransfer?.files;
        if (!files || !files.length) return;

        const input = zone.querySelector('[data-dropzone-input]');

        // Put the dropped file into the input field so that it gets submitted
        try {
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            input.files = transfer.files;
        } catch {
            // DataTransfer not supported — then only show the name
        }

        setFile(zone, files[0].name);
    });
});
