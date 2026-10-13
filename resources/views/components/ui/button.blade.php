@props([
    'variant' => 'secondary',
    'size' => 'sm',
    'icon' => null,
    'trailingIcon' => null,
    'href' => null,
    'label' => null,
    'disabled' => false,
    'disabledReason' => null,
])

@php
    // Without visible text the button becomes square and needs a label
    // for screen readers and the tooltip.
    $iconOnly = trim($slot->toHtml()) === '';

    $classes = collect([
        'ui-btn',
        'ui-btn-'.$size,
        'ui-btn-'.$variant,
        $iconOnly ? 'ui-btn-icon' : null,
    ])->filter()->implode(' ');

    /*
     * An unavailable action is greyed out, not hidden.
     * Otherwise identical rows would end up with different numbers of buttons
     * and the columns next to them would jump. The reason is in the tooltip.
     */
    $tag = $disabled ? 'button' : ($href ? 'a' : 'button');

    $title = $disabled ? ($disabledReason ?? $label) : $label;

    $extra = array_filter([
        'aria-label' => $iconOnly ? $label : null,
        'title' => ($iconOnly || $disabled) ? $title : null,
    ]);
@endphp

<{{ $tag }}
    @if($disabled)
        disabled aria-disabled="true"
    @elseif($href)
        href="{{ $href }}"
    @else
        type="{{ $attributes->get('type', 'button') }}"
    @endif
    {{ $attributes->except($disabled ? ['type', 'href'] : ['type'])->merge($extra)->merge(['class' => $classes]) }}
>
    @if($icon)<x-ui.nav-icon :name="$icon" />@endif
    @unless($iconOnly){{ $slot }}@endunless
    @if($trailingIcon)<x-ui.icon :name="$trailingIcon" />@endif
</{{ $tag }}>
