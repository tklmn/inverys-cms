{{--
    Backend-style confirmation instead of window.confirm.

        window.uiConfirm({ title, text, confirm, tone: 'accent' | 'danger', icon }) → Promise<boolean>

    Forms with data-confirm="…" (optionally data-confirm-title, data-confirm-label,
    data-confirm-tone) automatically ask here via shared/csp-actions.js.
--}}
<div
    id="global-confirm-modal"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="global-confirm-title"
    aria-describedby="global-confirm-text"
    style="display: none;"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
>
    <div id="global-confirm-backdrop" class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm" style="opacity: 0; transition: opacity 200ms ease;"></div>

    <div
        id="global-confirm-card"
        class="relative flex w-[28rem] max-w-full flex-col gap-4 rounded-xl border border-line bg-surface p-5 shadow-[var(--shadow-popover)]"
        style="opacity: 0; transform: scale(0.97); transition: opacity 200ms ease, transform 200ms ease;"
    >
        <div class="flex items-start gap-3">
            <span id="global-confirm-icon" class="ui-empty-icon shrink-0 bg-accent-soft text-accent"></span>
            <div class="min-w-0">
                <h2 id="global-confirm-title" class="ui-card-title"></h2>
                <p id="global-confirm-text" class="ui-help mt-1"></p>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" id="global-confirm-cancel" class="ui-btn ui-btn-md ui-btn-secondary">{{ __('translation.cancel') }}</button>
            <button type="button" id="global-confirm-ok" class="ui-btn ui-btn-md ui-btn-primary"></button>
        </div>
    </div>
</div>

<script @cspNonce>
(function () {
    const modal = document.getElementById('global-confirm-modal');
    const backdrop = document.getElementById('global-confirm-backdrop');
    const card = document.getElementById('global-confirm-card');
    const iconEl = document.getElementById('global-confirm-icon');
    const titleEl = document.getElementById('global-confirm-title');
    const textEl = document.getElementById('global-confirm-text');
    const cancelBtn = document.getElementById('global-confirm-cancel');
    const okBtn = document.getElementById('global-confirm-ok');
    const icons = {
        accent: @json(\App\Support\Icon::svg('circle-help')),
        danger: @json(\App\Support\Icon::svg('triangle-alert')),
        restore: @json(\App\Support\Icon::svg('rotate-ccw')),
    };
    const defaults = {
        title: @json(__('translation.confirm_title')),
        confirm: @json(__('translation.confirm_ok')),
    };
    let resolver = null;
    let previouslyFocused = null;

    function close(result) {
        if (!resolver) return;
        backdrop.style.opacity = '0';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.97)';
        setTimeout(() => { modal.style.display = 'none'; }, 200);
        document.removeEventListener('keydown', onKey, true);
        previouslyFocused?.focus?.();
        const done = resolver;
        resolver = null;
        done(result);
    }

    function onKey(e) {
        if (e.key === 'Escape') { e.preventDefault(); close(false); }
        else if (e.key === 'Tab') {
            // Focus stays inside the dialog
            e.preventDefault();
            (document.activeElement === okBtn ? cancelBtn : okBtn).focus();
        }
    }

    window.uiConfirm = function (options) {
        const o = typeof options === 'string' ? { text: options } : (options || {});
        if (resolver) close(false);

        const danger = o.tone === 'danger';
        iconEl.className = 'ui-empty-icon shrink-0 ' + (danger ? 'bg-critical-soft text-critical' : 'bg-accent-soft text-accent');
        iconEl.innerHTML = icons[o.icon] || (danger ? icons.danger : icons.accent);
        titleEl.textContent = o.title || defaults.title;
        textEl.textContent = o.text || '';
        textEl.hidden = !o.text;
        okBtn.textContent = o.confirm || defaults.confirm;
        okBtn.className = 'ui-btn ui-btn-md ' + (danger ? 'ui-btn-danger-solid' : 'ui-btn-primary');

        previouslyFocused = document.activeElement;
        modal.style.display = 'flex';
        requestAnimationFrame(() => requestAnimationFrame(() => {
            backdrop.style.opacity = '1';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        }));
        document.addEventListener('keydown', onKey, true);
        setTimeout(() => okBtn.focus(), 30);

        return new Promise((resolve) => { resolver = resolve; });
    };

    okBtn.addEventListener('click', () => close(true));
    cancelBtn.addEventListener('click', () => close(false));
    backdrop.addEventListener('click', () => close(false));
})();
</script>
