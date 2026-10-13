{{--
    Page behind the email change links: only the button takes action
    (EmailChangeController). mode: confirm | revert
--}}
<x-guest-layout>
    <x-slot name="title">{{ __('translation.email_change.'.$mode.'_title') }}</x-slot>
    <x-slot name="text">
        @if($valid)
            {{ __('translation.email_change.'.$mode.'_text', ['email' => $email]) }}
        @else
            {{ __('translation.email_change.invalid') }}
        @endif
    </x-slot>

    @if($valid)
        <form method="POST" action="{{ $action }}" class="auth-form">
            @csrf
            <x-ui.button type="submit" :variant="$mode === 'revert' ? 'danger-solid' : 'primary'" size="md" :icon="$mode === 'revert' ? 'shield-alert' : 'circle-check'" class="auth-submit">
                {{ __('translation.email_change.'.$mode.'_button') }}
            </x-ui.button>
        </form>
    @else
        <x-ui.button :href="route('login')" variant="secondary" size="md" icon="log-in" class="auth-submit">{{ __('translation.auth.back_to_login') }}</x-ui.button>
    @endif
</x-guest-layout>
