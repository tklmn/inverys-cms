@props([
    'name',
    'label' => null,
    'help' => null,
    'value' => null,
    'default' => null,
])

{{--
    Setting field with a default value.

    If the value differs from the default, a small button next to the label
    resets it. The behaviour is controlled by the respective view; this
    only holds the markup so that all plugins look the same.
--}}
@php($differs = (string) $value !== (string) $default)

<div {{ $attributes->merge(['class' => 'ui-field']) }} @if($errors->has($name)) data-invalid @endif>
    <div class="flex items-center gap-1.5">
        <label class="ui-label" for="{{ $name }}">{{ $label }}</label>

        @if($default !== null)
            <button
                type="button"
                data-reset="{{ $name }}"
                data-default="{{ $default }}"
                class="ui-btn ui-btn-xs ui-btn-icon ui-btn-ghost {{ $differs ? '' : 'hidden' }}"
                title="{{ __('translation.reset_to_default') }}"
                aria-label="{{ __('translation.reset_to_default') }}"
            >
                <x-ui.icon name="rotate-ccw" />
            </button>
        @endif
    </div>

    {{ $slot }}

    @if($help && ! $errors->has($name))
        <p class="ui-help">{{ $help }}</p>
    @endif

    @if($errors->has($name))
        <p class="ui-error"><x-ui.icon name="circle-alert" />{{ $errors->first($name) }}</p>
    @endif
</div>
