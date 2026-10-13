/**
 * Key figures (stats block) that count up from 0 when they scroll into view.
 *
 * The block renders the final value twice: visually hidden for screen readers
 * and as [data-count-up-value] (aria-hidden) for the eye. Only the visible copy
 * changes, so assistive technology, search engines and visitors without
 * JavaScript always get the real number. Nothing moves under
 * "prefers-reduced-motion: reduce".
 *
 * The number is taken as written: "1,200", "1.200", "98 %", "25+", "€4.9M"
 * keep their prefix, suffix, decimals and separators while counting.
 */

const DURATION = 1600;

/**
 * Splits "≈ 1,234.5 km" into prefix, number and suffix and works out which
 * separators the editor used. Returns null when there is nothing to count.
 */
export function parseFigure(text) {
    const match = /\d(?:[\d.,'   ]*\d)?/.exec(text);
    if (!match) return null;

    const raw = match[0];
    const prefix = text.slice(0, match.index);
    const suffix = text.slice(match.index + raw.length);
    const separators = raw.match(/[^\d]/g) || [];
    const unique = [...new Set(separators)];

    let decimal = '';
    let group = '';

    if (unique.length >= 2) {
        // "1,234.5" or "1.234,5": the last separator is the decimal one
        decimal = separators[separators.length - 1];
        group = unique.find((s) => s !== decimal) || '';
    } else if (unique.length === 1) {
        const sep = unique[0];
        const lastPart = raw.slice(raw.lastIndexOf(sep) + 1);
        // One separator followed by exactly three digits groups thousands ("1,200"),
        // otherwise it marks decimals ("4.9", "2,75"); spaces always group
        if (separators.length > 1 || lastPart.length === 3 || /\s|'/.test(sep)) group = sep;
        else decimal = sep;
    }

    const digits = raw.split('').filter((c) => /\d/.test(c) || c === decimal).join('').replace(decimal || '\u0000', '.');
    const value = parseFloat(digits);
    if (!Number.isFinite(value)) return null;

    const decimals = decimal ? raw.length - raw.lastIndexOf(decimal) - 1 : 0;

    return { prefix, suffix, value, decimals, decimal, group };
}

/** Formats a number the way the original figure was written */
export function formatFigure(figure, value) {
    const fixed = value.toFixed(figure.decimals);
    let [whole, fraction] = fixed.split('.');
    if (figure.group) whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, figure.group);

    return figure.prefix + whole + (fraction ? figure.decimal + fraction : '') + figure.suffix;
}

function animate(el, figure) {
    const start = performance.now();
    const step = (now) => {
        const progress = Math.min(1, (now - start) / DURATION);
        // Ease out: fast at first, settling on the final value
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = progress < 1 ? formatFigure(figure, figure.value * eased) : el.dataset.countUpFinal;
        if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
}

export function initCountUp() {
    const blocks = document.querySelectorAll('[data-count-up]');
    if (!blocks.length || !('IntersectionObserver' in window)) return;
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            observer.unobserve(entry.target);
            entry.target.querySelectorAll('[data-count-up-value]').forEach((el) => {
                if (el._figure) animate(el, el._figure);
            });
        }
    }, { threshold: 0.4 });

    blocks.forEach((block) => {
        block.querySelectorAll('[data-count-up-value]').forEach((el) => {
            const final = el.textContent.trim();
            const figure = parseFigure(final);
            if (!figure) return;
            el.dataset.countUpFinal = final;
            el._figure = figure;
            // Keep the final width so the layout does not jump while counting
            el.style.display = 'inline-block';
            el.style.minWidth = el.getBoundingClientRect().width + 'px';
            el.textContent = formatFigure(figure, 0);
        });
        observer.observe(block);
    });
}
