<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.confirm') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.secure_area') }}</x-slot>

    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
        @csrf

        @include('auth.partials.password', ['label' => __('translation.users.password'), 'autocomplete' => 'current-password', 'autofocus' => true])

        <x-ui.button type="submit" variant="primary" size="md" icon="circle-check" class="auth-submit">
            {{ __('translation.auth.confirm') }}
        </x-ui.button>
    </form>
</x-guest-layout>
