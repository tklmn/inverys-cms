<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.login_title') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.login_text') }}</x-slot>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <x-ui.field :label="__('translation.email')" name="email">
            <x-ui.input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username"
                :placeholder="__('translation.auth.input_placeholder.email')" />
        </x-ui.field>

        @include('auth.partials.password', [
            'label' => __('translation.users.password'),
            'autocomplete' => 'current-password',
            'link' => Route::has('password.request') ? ['url' => route('password.request'), 'text' => __('translation.auth.forgot_password')] : null,
        ])

        <x-ui.toggle name="remember" :label="__('translation.auth.remember_me')" />

        <x-ui.button type="submit" variant="primary" size="md" icon="log-in" class="auth-submit">
            {{ __('translation.auth.login') }}
        </x-ui.button>
    </form>

    @if (Route::has('register') && registration_open())
        <x-slot name="footer">
            <p class="auth-foot">
                {{ __('translation.auth.no_account') }}
                <a href="{{ route('register') }}" class="auth-link">{{ __('translation.auth.register') }}</a>
            </p>
        </x-slot>
    @endif
</x-guest-layout>
