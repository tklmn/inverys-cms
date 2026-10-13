<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.forgot_password') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.forgot_password_text') }}</x-slot>

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
        @csrf

        <x-ui.field :label="__('translation.email')" name="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="email"
                :placeholder="__('translation.auth.input_placeholder.email')" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" size="md" icon="mail" class="auth-submit">
            {{ __('translation.auth.password_reset_link') }}
        </x-ui.button>
    </form>

    <x-slot name="footer">
        <p class="auth-foot">
            <a href="{{ route('login') }}" class="auth-link">{{ __('translation.auth.back_to_login') }}</a>
        </p>
    </x-slot>
</x-guest-layout>
