// Frontend specific JavaScript
import '../shared/bootstrap';
import { initDropdowns } from './dropdowns';
import { initCountUp } from './count-up';

// GLightbox — lazy-init only when .glightbox elements exist on the page
import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.min.css';

// Labels of the lightbox buttons in the page language. The
// gallery block outputs them as JSON (id="glightbox-i18n"); without them
// GLightbox keeps its English defaults.
function lightboxLabels() {
    try {
        return JSON.parse(document.getElementById('glightbox-i18n')?.textContent || '{}');
    } catch {
        return {};
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initDropdowns();
    initCountUp();

    if (document.querySelector('.glightbox')) {
        const lightbox = GLightbox({ selector: '.glightbox' });
        const labels = lightboxLabels();

        lightbox.on('open', () => {
            for (const [selector, text] of [['.gclose', labels.close], ['.gprev', labels.previous], ['.gnext', labels.next]]) {
                const button = document.querySelector('#glightbox-body ' + selector);
                if (button && text) button.setAttribute('aria-label', text);
            }
        });
    }
});
