@props([
    'name',
    'value' => null,
    'id' => null,
    'picker',
    'placeholder' => null,
    'disabled' => false,
    'dark' => false,
])

{{--
    Image field: preview, path, "Library" and "Remove" in one row.

    `picker`  — id of an x-ui.media-picker on the page (one is enough for
                any number of image fields; the button sets the target)
    `dark`    — preview on a dark background, e.g. for a logo in dark mode

    The preview follows every change — typing, picking from the library,
    removing, resetting (resources/js/backend/ui/image-field.js).
    Further attributes (data-field for x-ui.setting-field …) end up on the input.
--}}
@php
    $fieldId = $id ?? str_replace(['[', ']'], ['-', ''], $name);
    $src = $value ? (preg_match('#^https?://#', (string) $value) ? $value : asset($value)) : '';
@endphp

<div class="ui-image-field" data-image-field>
    <span @class(['ui-image-preview', 'ui-image-preview-dark' => $dark, 'is-empty' => ! $value])>
        <img src="{{ $src }}" alt="" data-image-preview @if(! $value) hidden @endif>
        <x-ui.icon name="image" data-image-empty />
    </span>

    <div class="ui-image-controls">
        <x-ui.input
            :id="$fieldId"
            :name="$name"
            :value="$value"
            :placeholder="$placeholder ?? __('Enter URL or select from library')"
            :disabled="$disabled"
            data-image-input
            {{ $attributes }}
        />
        <div class="flex gap-2">
            <x-ui.button size="md" icon="images" :disabled="$disabled" data-media-picker-open="{{ $picker }}" data-media-picker-target="{{ $fieldId }}">{{ __('Library') }}</x-ui.button>
            <x-ui.button size="md" variant="ghost" icon="x" :disabled="$disabled" data-image-clear :label="__('translation.media.remove_image')" />
        </div>
    </div>
</div>
