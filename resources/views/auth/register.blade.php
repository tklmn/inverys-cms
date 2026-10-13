<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.register_title') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.register_text') }}</x-slot>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="auth-row">
            <x-ui.field :label="__('translation.users.first_name')" name="first_name">
                <x-ui.input id="first_name" name="first_name" :value="old('first_name')" required autofocus autocomplete="given-name" />
            </x-ui.field>

            <x-ui.field :label="__('translation.users.last_name')" name="last_name">
                <x-ui.input id="last_name" name="last_name" :value="old('last_name')" required autocomplete="family-name" />
            </x-ui.field>
        </div>

        <x-ui.field :label="__('translation.name')" name="name">
            <x-ui.input id="name" name="name" :value="old('name')" required autocomplete="username" />
        </x-ui.field>

        <x-ui.field :label="__('translation.email')" name="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autocomplete="email"
                :placeholder="__('translation.auth.input_placeholder.email')" />
        </x-ui.field>

        @include('auth.partials.password', ['label' => __('translation.users.password'), 'autocomplete' => 'new-password'])
        @include('auth.partials.password', ['name' => 'password_confirmation', 'label' => __('translation.users.confirm_password'), 'autocomplete' => 'new-password'])
        @include('auth.partials.password-hints', ['rules' => $passwordRules ?? [], 'policy' => $passwordPolicy ?? []])

        <x-ui.button type="submit" variant="primary" size="md" icon="user-plus" class="auth-submit">
            {{ __('translation.auth.register') }}
        </x-ui.button>
    </form>

    <x-slot name="footer">
        <p class="auth-foot">
            {{ __('translation.auth.already_registered') }}
            <a href="{{ route('login') }}" class="auth-link">{{ __('translation.auth.login') }}</a>
        </p>
    </x-slot>
</x-guest-layout>
