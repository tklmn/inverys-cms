@props(['disabled' => false])

{{--
    Alias for third-party plugins that forwards to x-ui.input — one implementation.
    New code uses <x-ui.input> inside an <x-ui.field>.
--}}
<x-ui.input :disabled="$disabled" {{ $attributes }} />
