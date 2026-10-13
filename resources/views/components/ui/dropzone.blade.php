@props([
    'name' => 'package',
    'accept' => '.zip',
    'label' => null,
    'hint' => null,
    'icon' => 'upload',
    'compact' => false,
])

{{--
    Drop area for files.

    Replaces the raw <input type="file">, which depending on the browser shows
    up as a grey button with "No file chosen" and cannot be styled. Here
    the input is invisible; the area itself is the label and the drag & drop
    target.

    `compact` — single line (icon left, text right), for fields next to a
                preview such as the profile picture.
--}}
@php($id = $name.'-'.Str::random(6))

<div
    class="ui-dropzone{{ $compact ? ' ui-dropzone-compact' : '' }}"
    data-dropzone
    role="group"
>
    <label for="{{ $id }}" class="ui-dropzone-area">
        <x-ui.icon :name="$icon" class="text-2xl text-fg-subtle" />

        <span class="ui-dropzone-title">
            {{ $label ?? __('translation.dropzone.choose') }}
        </span>

        <span class="ui-dropzone-hint" data-dropzone-hint>
            {{ $hint ?? __('translation.dropzone.hint') }}
        </span>

        <input
            type="file"
            id="{{ $id }}"
            name="{{ $name }}"
            accept="{{ $accept }}"
            class="sr-only"
            data-dropzone-input
            {{ $attributes }}
        >
    </label>

    <p class="ui-dropzone-file hidden" data-dropzone-file></p>
</div>
