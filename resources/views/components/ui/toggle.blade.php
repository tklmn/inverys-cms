@props([
    'name',
    'checked' => false,
    'label' => null,
    'onValue' => 'on',
    'offValue' => 'off',
    'multiple' => false,
    'bare' => false,
    'disabled' => false,
])

{{--
    On/off switch — the only yes/no control in the backend.
    Checkboxes are gone, including in selection lists.

    The hidden field in front ensures the off state is submitted too —
    an unchecked checkbox would otherwise send nothing at all, and
    server-side rules like `required|in:on,off` would kick in.
    So in the controller read `$request->boolean()`, never `has()`.

    `multiple` — part of a multi-select (name="roles[]"): no off field,
                 otherwise "roles[]=off" would reach the server.
    `bare`     — without its own <label>, for x-ui.switch-row, whose whole row
                 already is the label (nested labels are invalid).

    Additional attributes (id, class, data-*) end up on the checkbox.
--}}
@php($tag = $bare ? 'span' : 'label')

<{{ $tag }} class="ui-toggle">
    @unless($multiple)
        {{-- Disabled: do not submit the off field either --}}
        <input type="hidden" name="{{ $name }}" value="{{ $offValue }}" @disabled($disabled)>
    @endunless

    <input
        type="checkbox"
        name="{{ $name }}"
        value="{{ $onValue }}"
        @checked($checked)
        @disabled($disabled)
        {{ $attributes->merge(['class' => 'ui-toggle-input']) }}
    >

    <span class="ui-toggle-track" aria-hidden="true"></span>

    @if($label)<span class="ui-toggle-label">{{ $label }}</span>@endif
</{{ $tag }}>
