/**
 * Tab bars (.ui-tabs) that are wider than their space.
 *
 * The bar scrolls sideways; data-overflow="start end" tells the CSS on
 * which edge tabs are still hidden — that edge fades out. Without this
 * hint a clipped tab ("Formu…") looked like a bug.
 * The active tab is scrolled into view on load.
 */

function update(bar) {
    const max = bar.scrollWidth - bar.clientWidth;
    const edges = [];
    if (bar.scrollLeft > 2) edges.push('start');
    if (bar.scrollLeft < max - 2) edges.push('end');

    if (edges.length) bar.dataset.overflow = edges.join(' ');
    else delete bar.dataset.overflow;
}

document.querySelectorAll('.ui-tabs').forEach((bar) => {
    bar.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest', inline: 'nearest' });

    update(bar);
    bar.addEventListener('scroll', () => update(bar), { passive: true });

    if ('ResizeObserver' in window) new ResizeObserver(() => update(bar)).observe(bar);
});
