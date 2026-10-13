@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-800'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right inset-s-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left inset-e-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative dropdown-wrapper">
    <div class="dropdown-trigger" aria-haspopup="true" aria-expanded="false">
        {{ $trigger }}
    </div>

    <div
        role="menu"
        class="dropdown-content absolute z-50 mt-2 {{ $width }} rounded-md {{ $alignmentClasses }}"
        style="display: none; opacity: 0; transform: scale(0.95); transition: opacity 200ms ease, transform 200ms ease;"
    >
        <div class="rounded-md border border-gray-200 dark:border-gray-700 shadow-sm {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>

<script @cspNonce>
(function () {
    // Find the most recently added dropdown wrapper (the one just rendered)
    const wrappers = document.querySelectorAll('.dropdown-wrapper');
    const wrapper  = wrappers[wrappers.length - 1];
    if (!wrapper) return;

    const trigger = wrapper.querySelector('.dropdown-trigger');
    const content = wrapper.querySelector('.dropdown-content');
    let open = false;

    function openDropdown() {
        open = true;
        trigger.setAttribute('aria-expanded', 'true');
        content.style.display = 'block';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                content.style.opacity = '1';
                content.style.transform = 'scale(1)';
            });
        });
    }

    function closeDropdown() {
        open = false;
        trigger.setAttribute('aria-expanded', 'false');
        content.style.opacity = '0';
        content.style.transform = 'scale(0.95)';
        setTimeout(() => {
            if (!open) content.style.display = 'none';
        }, 75);
    }

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        open ? closeDropdown() : openDropdown();
    });

    // Close when clicking inside the content (links etc.)
    content.addEventListener('click', function () {
        closeDropdown();
    });

    // Close on 'close' event bubbled from inside
    wrapper.addEventListener('close', function () {
        closeDropdown();
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) {
            closeDropdown();
        }
    });
})();
</script>
