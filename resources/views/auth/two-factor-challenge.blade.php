<x-guest-layout>
    <x-slot name="title">{{ __('auth.two_factor.challenge_title') }}</x-slot>
    <x-slot name="text">{{ $method === 'totp' ? __('auth.two_factor.totp_prompt') : __('auth.two_factor.email_prompt') }}</x-slot>

    <form method="POST" action="{{ route('two-factor.verify') }}" id="two-factor-form" class="auth-form">
        @csrf
        <input type="hidden" name="use_recovery" id="use_recovery" value="0">

        {{-- Texts for both modes (code / recovery code) on the field, the script swaps them --}}
        <x-ui.field :label="__('auth.two_factor.code_label')" name="code" data-two-factor-field
            data-code-label="{{ __('auth.two_factor.code_label') }}" data-code-placeholder="{{ __('auth.two_factor.code_placeholder') }}"
            data-recovery-label="{{ __('auth.two_factor.recovery_label') }}" data-recovery-placeholder="{{ __('auth.two_factor.recovery_placeholder') }}">
            <x-ui.input id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="19"
                :placeholder="__('auth.two_factor.code_placeholder')" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" size="md" icon="circle-check" class="auth-submit">
            {{ __('auth.two_factor.verify_button') }}
        </x-ui.button>
    </form>

    <x-slot name="footer">
        <p class="auth-foot">
            <button type="button" class="auth-text-btn" id="toggle-recovery"
                data-use-recovery="{{ __('auth.two_factor.use_recovery') }}" data-use-code="{{ __('auth.two_factor.use_auth_code') }}">
                {{ __('auth.two_factor.use_recovery') }}
            </button>
        </p>

        @if ($method === 'email')
            <form method="POST" action="{{ route('two-factor.resend') }}" class="auth-foot">
                @csrf
                <button type="submit" class="auth-text-btn">{{ __('auth.two_factor.resend_code') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="auth-foot">
            @csrf
            <button type="submit" class="auth-text-btn">{{ __('translation.logout') }}</button>
        </form>
    </x-slot>

    <script @cspNonce>
        (function () {
            var toggle = document.getElementById('toggle-recovery');
            var field = document.querySelector("[data-two-factor-field]");
            var mode = document.getElementById('use_recovery');
            var input = document.getElementById('code');
            if (!toggle || !field || !mode || !input) return;
            var label = field.querySelector('.ui-label');

            toggle.addEventListener('click', function () {
                var recovery = mode.value !== '1';
                mode.value = recovery ? '1' : '0';
                label.textContent = field.getAttribute(recovery ? 'data-recovery-label' : 'data-code-label');
                input.placeholder = field.getAttribute(recovery ? 'data-recovery-placeholder' : 'data-code-placeholder');
                input.inputMode = recovery ? 'text' : 'numeric';
                input.maxLength = recovery ? 14 : 19;
                toggle.textContent = toggle.getAttribute(recovery ? 'data-use-code' : 'data-use-recovery');
                input.value = '';
                input.focus();
            });
        })();
    </script>
</x-guest-layout>
