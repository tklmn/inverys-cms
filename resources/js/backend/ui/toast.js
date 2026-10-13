/**
 * Notifications (toasts).
 *
 * The server already renders the messages into the markup — this module only
 * takes care of dismissing them. So the message is visible even
 * when the JavaScript does not load; it simply stays put then.
 *
 * window.uiToast(text, tone) additionally shows a message at runtime
 * — for actions that work without a page change.
 */

import { iconHtml } from './icons.js';
import { t } from './i18n.js';

const LIFETIME = 5000;

const TONE_ICONS = {
    positive: 'circle-check',
    caution: 'triangle-alert',
    critical: 'circle-alert',
    neutral: 'info',
};
const LEAVE = 200;

function dismiss(toast) {
    if (toast.dataset.leaving !== undefined) return;

    toast.dataset.leaving = '';
    setTimeout(() => toast.remove(), LEAVE);
}

function arm(toast) {
    // The lifetime is set as a CSS variable on the element so the bar and
    // the timer do not drift apart.
    const life = Number(toast.dataset.life) || LIFETIME;
    toast.style.setProperty('--ui-toast-life', `${life}ms`);

    let timer = setTimeout(() => dismiss(toast), life);
    let remaining = life;
    let startedAt = Date.now();

    // While the pointer rests on it, the time is paused —
    // otherwise a message disappears right while it is being read.
    toast.addEventListener('mouseenter', () => {
        clearTimeout(timer);
        remaining -= Date.now() - startedAt;
    });

    toast.addEventListener('mouseleave', () => {
        startedAt = Date.now();
        timer = setTimeout(() => dismiss(toast), Math.max(remaining, LEAVE));
    });
}

function container() {
    let box = document.querySelector('[data-toasts]');

    if (!box) {
        box = document.createElement('div');
        box.className = 'ui-toasts';
        box.dataset.toasts = '';
        box.setAttribute('aria-live', 'polite');
        document.body.appendChild(box);
    }

    return box;
}

function push(text, tone = 'positive') {
    const toast = document.createElement('div');
    toast.className = `ui-toast ui-toast-${tone}`;
    toast.dataset.toast = '';
    toast.setAttribute('role', tone === 'critical' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'ui-toast-icon';
    icon.innerHTML = iconHtml(TONE_ICONS[tone] || 'info');

    const body = document.createElement('p');
    body.className = 'ui-toast-text';
    body.textContent = text;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ui-toast-close';
    close.dataset.toastClose = '';
    close.setAttribute('aria-label', t('close', 'Close'));
    close.innerHTML = iconHtml('x');

    toast.append(icon, body, close);
    container().appendChild(toast);
    arm(toast);

    return toast;
}

document.addEventListener('click', (event) => {
    const close = event.target.closest('[data-toast-close]');
    if (close) dismiss(close.closest('[data-toast]'));
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toast]').forEach(arm);
});

window.uiToast = push;
