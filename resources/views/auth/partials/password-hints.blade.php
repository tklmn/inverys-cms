{{--
    Password rules with live checking.

    @include('auth.partials.password-hints')
    @include('auth.partials.password-hints', ['for' => 'update_password'])

    Rules come from App\Support\PasswordPolicy (security settings).
    `for` is the id of the password field that is checked live (default: password).
--}}
@php
    $rules = ! empty($rules) ? $rules : \App\Support\PasswordPolicy::rules();
    $policy = ! empty($policy) ? $policy : \App\Support\PasswordPolicy::policy();
    $for = $for ?? 'password';
@endphp
@if(! empty($rules) && ! empty($policy))
    <div class="auth-hints" id="password-hints" data-for="{{ $for }}">
        <span class="auth-hints-title">{{ __('translation.auth.password_requirements') }}</span>
        <ul>
            <li data-rule="min_length">{{ $rules[0] }}</li>
            @if($policy['require_uppercase'])
                <li data-rule="uppercase">{{ __('translation.auth.password_rule_uppercase') }}</li>
            @endif
            @if($policy['require_numbers'])
                <li data-rule="numbers">{{ __('translation.auth.password_rule_numbers') }}</li>
            @endif
            @if($policy['require_symbols'])
                <li data-rule="symbols">{{ __('translation.auth.password_rule_symbols') }}</li>
            @endif
        </ul>
    </div>
    <script @cspNonce>
        (function () {
            var policy = @json($policy);
            var hints = document.getElementById('password-hints');
            var input = hints && document.getElementById(hints.getAttribute('data-for'));
            if (!input || !hints) return;

            function mark(name, ok) {
                var item = hints.querySelector('[data-rule="' + name + '"]');
                if (item) item.setAttribute('data-valid', ok ? '1' : '0');
            }

            input.addEventListener('input', function () {
                var value = input.value;
                mark('min_length', value.length >= policy.min_length);
                if (policy.require_uppercase) mark('uppercase', /[a-z]/.test(value) && /[A-Z]/.test(value));
                if (policy.require_numbers) mark('numbers', /\d/.test(value));
                if (policy.require_symbols) mark('symbols', /[^a-zA-Z0-9]/.test(value));
            });
        })();
    </script>
@endif
