<x-guest-layout>
    <x-slot name="title">{{ __('translation.auth.verify_email') }}</x-slot>
    <x-slot name="text">{{ __('translation.auth.verify_email_text') }}</x-slot>

    <div class="auth-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" variant="primary" size="md" icon="mail" class="auth-submit">
                {{ __('translation.auth.resend_verification_email') }}
            </x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="md" icon="log-out" class="auth-submit">
                {{ __('translation.logout') }}
            </x-ui.button>
        </form>
    </div>
</x-guest-layout>
