@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'href' => null,
    'iconOnly' => false,
    'label' => null,
])

{{--
    Alias that forwards to x-ui.button.

    This leaves only ONE button implementation. The alias exists
    for third-party plugins; new code uses <x-ui.button>
    directly.

    Its colour variants ("edit", "success" …) are mapped onto the four
    weights of the design system.
--}}
@php
    $variants = [
        'primary' => 'primary',
        'success' => 'primary',
        'secondary' => 'secondary',
        'edit' => 'secondary',
        'ghost' => 'ghost',
        'danger' => 'danger',
    ];

    $sizes = ['sm' => 'sm', 'md' => 'md', 'lg' => 'md'];

    // Map Bootstrap Icons names to Lucide
    $icons = [
        'bi bi-plus-lg' => 'plus',
        'bi bi-plus' => 'plus',
        'bi bi-check-lg' => 'check',
        'bi bi-check2' => 'check',
        'bi bi-pencil' => 'pencil',
        'bi bi-trash' => 'trash-2',
        'bi bi-eye' => 'eye',
        'bi bi-x-lg' => 'x',
        'bi bi-x-circle' => 'circle-x',
        'bi bi-search' => 'search',
        'bi bi-download' => 'download',
        'bi bi-upload' => 'upload',
        'bi bi-cloud-upload' => 'upload',
        'bi bi-arrow-counterclockwise' => 'rotate-ccw',
        'bi bi-arrow-left' => 'arrow-left',
        'bi bi-gear' => 'settings',
        'bi bi-tags' => 'tag',
        'bi bi-inbox' => 'inbox',
        'bi bi-clipboard' => 'copy',
        'bi bi-images' => 'image',
        'bi bi-list' => 'list',
        'bi bi-diagram-3' => 'folder-tree',
        'bi bi-check-all' => 'check',
        'bi bi-code-slash' => 'code',
        'bi bi-sliders' => 'sliders-horizontal',
        'bi bi-envelope' => 'mail',
        'bi bi-send' => 'send',
    ];

    $resolvedIcon = $icon ? ($icons[trim($icon)] ?? null) : null;
@endphp

<x-ui.button
    :variant="$variants[$variant] ?? 'primary'"
    :size="$sizes[$size] ?? 'md'"
    :icon="$resolvedIcon"
    :href="$href"
    :label="$label"
    :disabled="$iconOnly && false"
    {{ $attributes }}
>{{ $slot }}</x-ui.button>
