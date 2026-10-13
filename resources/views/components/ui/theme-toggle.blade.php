@props([
    'buttonClass' => 'ui-btn ui-btn-md ui-btn-icon ui-btn-ghost',
])

@php
    $current = \App\Helpers\ThemeHelper::getTheme();
    $next = $current === 'dark' ? 'light' : 'dark';

    $label = $next === 'dark'
        ? __('translation.dashboard.theme.dark')
        : __('translation.dashboard.theme.light');
@endphp

{{--
    Toggle light/dark.

    Works in the frontend as in the backend: for logged-in users the choice
    is stored permanently, for guests in their session. It is a real
    form and therefore usable without JavaScript too.

    buttonClass can be overridden so that the button fits the look
    of the respective theme.
--}}
<form method="POST" action="{{ route('theme.toggle') }}" {{ $attributes->merge(['class' => 'inline-flex']) }}>
    @csrf
    <input type="hidden" name="theme" value="{{ $next }}">

    <button type="submit" class="{{ $buttonClass }}" title="{{ $label }}" aria-label="{{ $label }}">
        <x-ui.icon :name="$current === 'dark' ? 'sun' : 'moon'" />
    </button>
</form>
