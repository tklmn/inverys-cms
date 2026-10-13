{{-- Globale Loeschbestaetigung --}}
<div
    id="global-delete-modal"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="global-delete-modal-title"
    aria-describedby="global-delete-modal-subtitle"
    style="display: none;"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
>
    <div
        id="global-delete-modal-backdrop"
        class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm"
        style="opacity: 0; transition: opacity 200ms ease;"
    ></div>

    <div
        id="global-delete-modal-card"
        class="relative flex w-[26rem] max-w-full flex-col gap-4 rounded-xl border border-line bg-surface p-5 shadow-[var(--shadow-popover)]"
        style="opacity: 0; transform: scale(0.97); transition: opacity 200ms ease, transform 200ms ease;"
    >
        <div class="flex items-start gap-3">
            <span class="ui-empty-icon shrink-0 bg-critical-soft text-critical">
                <x-ui.icon name="trash-2" />
            </span>
            <div class="min-w-0">
                <h2 id="global-delete-modal-title" class="ui-card-title" data-default="{{ __('translation.confirm_delete') }}">
                    {{ __('translation.confirm_delete') }}
                </h2>
                <p id="global-delete-modal-subtitle" class="ui-help mt-1" data-default="{{ __('This action cannot be undone.') }}">
                    {{ __('This action cannot be undone.') }}
                </p>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" id="global-delete-modal-cancel" class="ui-btn ui-btn-md ui-btn-secondary">
                {{ __('translation.cancel') }}
            </button>
            <button type="button" id="global-delete-modal-confirm" class="ui-btn ui-btn-md ui-btn-danger-solid">
                <x-ui.icon name="trash-2" />
                {{ __('translation.delete') }}
            </button>
        </div>
    </div>
</div>

<script @cspNonce>
(function () {
    const modal    = document.getElementById('global-delete-modal');
    const backdrop = document.getElementById('global-delete-modal-backdrop');
    const card     = document.getElementById('global-delete-modal-card');
    const titleEl  = document.getElementById('global-delete-modal-title');
    const subtitleEl = document.getElementById('global-delete-modal-subtitle');
    const cancelBtn  = document.getElementById('global-delete-modal-cancel');
    const confirmBtn = document.getElementById('global-delete-modal-confirm');
    const defaultTitle = titleEl.dataset.default;
    const defaultSubtitle = subtitleEl.dataset.default;
    let currentFormId = null;
    let previouslyFocused = null;

    function openDeleteModal(formId, title, subtitle) {
        currentFormId = formId;
        previouslyFocused = document.activeElement;
        titleEl.textContent = title || defaultTitle;
        subtitleEl.textContent = subtitle || defaultSubtitle;
        modal.style.display = 'flex';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                backdrop.style.opacity = '1';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
                cancelBtn.focus();
            });
        });
    }

    function closeDeleteModal() {
        backdrop.style.opacity = '0';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            modal.style.display = 'none';
            currentFormId = null;
            if (previouslyFocused && previouslyFocused.focus) {
                previouslyFocused.focus();
                previouslyFocused = null;
            }
        }, 150);
    }

    // Trap focus within the delete modal
    card.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        const focusable = card.querySelectorAll('button:not([disabled])');
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('open-delete-modal', function (e) {
        openDeleteModal(e.detail.formId, e.detail.title, e.detail.subtitle);
    });

    cancelBtn.addEventListener('click', closeDeleteModal);

    backdrop.addEventListener('click', closeDeleteModal);

    card.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    confirmBtn.addEventListener('click', function () {
        if (currentFormId) {
            const form = document.getElementById(currentFormId);
            if (form) form.submit();
        }
        closeDeleteModal();
    });

    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            closeDeleteModal();
        }
    });
})();
</script>
