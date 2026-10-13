@props([
    'name',
    'label',
    'hint' => null,
    'checked' => false,
    'onValue' => 'on',
    'offValue' => 'off',
    'default' => null,
    'multiple' => false,
    'inputId' => null,
    'inputClass' => '',
    'titleClass' => '',
    'bleed' => true,
    'disabled' => false,
])

{{--
    A yes/no row: label on the left, switch on the right, the whole row
    is clickable. Used alike for single settings ("User active")
    and for selection lists (roles, permissions, HTTP codes).

    `multiple`   — selection list: name="roles[]", on-value = the ID, no off field
    `default`    — shows the reset button from x-ui.setting-field
    `inputId`,
    `inputClass` — end up on the checkbox (e.g. for scripts)
    `titleClass` — e.g. font-mono for technical names
    `bleed`      — extends by the padding to the left/right so the text
                   lines up with the fields above. Turn off in grids of
                   many rows, otherwise the areas touch.

    No @if inside the <x-ui.toggle> tag: Blade cannot compile that, the tag
    then ends up raw in the HTML and the switch is missing. Null attributes,
    on the other hand, are simply dropped from the attribute list.
--}}
@php
    // Explicit for: otherwise, in rows with a reset button, the button would be
    // the first labelable element, and a click on the text would reset.
    $switchId = $inputId ?? 'switch-'.Str::random(8);
@endphp

<label for="{{ $switchId }}" {{ $attributes->merge(['class' => 'ui-check-row justify-between'.($bleed ? ' -mx-2' : '').($disabled ? ' is-disabled' : '')]) }}>
    <span class="min-w-0">
        <span class="ui-check-title flex items-center gap-1.5 {{ $titleClass }}">
            {{ $label }}

            @if($default !== null)
                <button
                    type="button"
                    data-reset="{{ $name }}"
                    data-default="{{ $default }}"
                    class="ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost {{ (string) ($checked ? $onValue : $offValue) !== (string) $default ? '' : 'hidden' }}"
                    title="{{ __('translation.reset_to_default') }}"
                    aria-label="{{ __('translation.reset_to_default') }}"
                >
                    <x-ui.icon name="rotate-ccw" />
                </button>
            @endif
        </span>

        @if($hint)<span class="ui-check-hint block">{{ $hint }}</span>@endif
    </span>

    <x-ui.toggle
        bare
        :name="$name"
        :checked="$checked"
        :on-value="$onValue"
        :off-value="$offValue"
        :multiple="$multiple"
        :id="$switchId"
        class="{{ $inputClass }}"
        :data-field="$default !== null ? $name : null"
        :disabled="$disabled"
    />
</label>
