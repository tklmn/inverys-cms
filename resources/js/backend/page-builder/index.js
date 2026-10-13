import { PageBuilder } from './page-builder.js';

// Initialize page builders on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-page-builder]').forEach(el => {
        try {
            const config = JSON.parse(el.dataset.pageBuilder);
            // Instance on the element so that e.g. the content mode switch can reach it
            el.pageBuilder = new PageBuilder(el, config);
        } catch (e) {
            // Silent failure — invalid config, skip this builder instance
        }
    });
});
