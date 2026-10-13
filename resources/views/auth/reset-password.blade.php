<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.reset_password') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.reset_password_text') }}</x-slot>

    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui.field :label="__('translation.email')" name="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email', $email)" required autocomplete="username" />
        </x-ui.field>

        @include('auth.partials.password', ['label' => __('translation.users.password'), 'autocomplete' => 'new-password', 'autofocus' => true])
        @include('auth.partials.password', ['name' => 'password_confirmation', 'label' => __('translation.users.confirm_password'), 'autocomplete' => 'new-password'])
        @include('auth.partials.password-hints', ['rules' => $passwordRules ?? [], 'policy' => $passwordPolicy ?? []])

        <x-ui.button type="submit" variant="primary" size="md" icon="lock" class="auth-submit">
            {{ __('translation.auth.reset_password') }}
        </x-ui.button>
    </form>
</x-guest-layout>
