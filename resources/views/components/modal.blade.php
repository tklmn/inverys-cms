@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

<div
    id="modal-{{ $name }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-{{ $name }}-title"
    class="modal-container fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: {{ $show ? 'block' : 'none' }};"
    data-modal-name="{{ $name }}"
    data-focusable="{{ $attributes->has('focusable') ? 'true' : 'false' }}"
>
    <div
        class="modal-backdrop fixed inset-0 transform transition-all"
        style="transition: opacity 300ms ease;"
    >
        <div class="absolute inset-0 bg-ink-950/40 backdrop-blur-sm"></div>
    </div>

    <div
        class="modal-panel mb-6 overflow-hidden rounded-xl border border-line bg-surface shadow-[var(--shadow-popover)] transition-all sm:mx-auto sm:w-full {{ $maxWidth }}"
        style="transition: opacity 300ms ease, transform 300ms ease;"
    >
        {{ $slot }}
    </div>
</div>

<script @cspNonce>
(function () {
    const modalEl = document.getElementById('modal-{{ $name }}');
    if (!modalEl) return;

    const name     = '{{ $name }}';
    const backdrop = modalEl.querySelector('.modal-backdrop');
    const panel    = modalEl.querySelector('.modal-panel');
    const focusable = modalEl.dataset.focusable === 'true';

    function getFocusables() {
        const selector = 'a, button, input:not([type="hidden"]), textarea, select, details, [tabindex]:not([tabindex="-1"])';
        return [...modalEl.querySelectorAll(selector)].filter(el => !el.hasAttribute('disabled'));
    }

    function openModal() {
        modalEl.style.display = 'block';
        document.body.classList.add('overflow-y-hidden');
        // Trigger enter transition
        backdrop.style.opacity = '0';
        panel.style.opacity = '0';
        panel.style.transform = 'translateY(1rem) scale(0.95)';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                backdrop.style.opacity = '1';
                panel.style.opacity = '1';
                panel.style.transform = 'translateY(0) scale(1)';
            });
        });
        if (focusable) {
            setTimeout(() => {
                const first = getFocusables()[0];
                if (first) first.focus();
            }, 100);
        }
    }

    function closeModal() {
        // Trigger leave transition
        backdrop.style.opacity = '0';
        panel.style.opacity = '0';
        panel.style.transform = 'translateY(1rem) scale(0.95)';
        setTimeout(() => {
            modalEl.style.display = 'none';
            document.body.classList.remove('overflow-y-hidden');
        }, 200);
    }

    // Listen for open-modal CustomEvent
    window.addEventListener('open-modal', function (e) {
        if (e.detail === name) openModal();
    });

    // Listen for close-modal CustomEvent
    window.addEventListener('close-modal', function (e) {
        if (e.detail === name) closeModal();
    });

    // Close on 'close' event bubbling up from inside the modal
    modalEl.addEventListener('close', function () {
        closeModal();
    });

    // Close on backdrop click
    backdrop.addEventListener('click', function () {
        closeModal();
    });

    // Escape key
    window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modalEl.style.display !== 'none') {
            closeModal();
        }
    });

    // Tab trap
    modalEl.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        const focusables = getFocusables();
        if (!focusables.length) { e.preventDefault(); return; }
        const first = focusables[0];
        const last  = focusables[focusables.length - 1];
        if (e.shiftKey) {
            if (document.activeElement === first) { e.preventDefault(); last.focus(); }
        } else {
            if (document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    // If shown on page load, init state
    if (modalEl.style.display !== 'none') {
        backdrop.style.opacity = '1';
        panel.style.opacity = '1';
        panel.style.transform = 'translateY(0) scale(1)';
        document.body.classList.add('overflow-y-hidden');
    }
})();
</script>
