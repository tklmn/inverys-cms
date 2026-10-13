@php
    $twoFactor = app(\App\Services\TwoFactorService::class);
    $user = auth()->user();
    $isGloballyEnabled = setting('security.two_factor_enabled', 'on') === 'on';
    $isUserDisabled = $user->getSetting('two_factor_disabled') === '1';
    $isActive = $isGloballyEnabled && !$isUserDisabled;
    $hasTotpEnabled = $user->hasTotpEnabled();
    $method = $twoFactor->userMethod($user);
    $showSetup = session('two_factor_setup');
    $secret = session('two_factor_secret');
    $qrUri = session('two_factor_qr_uri');
    $recoveryCodes = session('two_factor_recovery_codes');
    $remainingCodes = $twoFactor->remainingRecoveryCodes($user);
@endphp

{{--
    Two-factor login: status, on/off for your own account, switching to
    an authenticator app. Every change requires a valid code.
--}}
@if (!$isGloballyEnabled)
    <x-ui.empty icon="shield-off">{{ __('auth.two_factor.globally_disabled') }}</x-ui.empty>
@else
    <div class="flex flex-col gap-4">
        {{-- Status --}}
        <div class="flex flex-wrap items-center gap-2">
            @if (! $isActive)
                <x-ui.badge>{{ __('auth.two_factor.status_disabled') }}</x-ui.badge>
                <span class="ui-help">{{ __('auth.two_factor.user_disabled_desc') }}</span>
            @elseif ($hasTotpEnabled)
                <x-ui.badge tone="positive">{{ __('auth.two_factor.method_totp') }}</x-ui.badge>
                <span class="ui-help">{{ __('auth.two_factor.totp_active_desc') }}</span>
            @else
                <x-ui.badge tone="accent">{{ __('auth.two_factor.method_email') }}</x-ui.badge>
                <span class="ui-help">{{ __('auth.two_factor.email_active_desc') }}</span>
            @endif
        </div>

        {{-- Recovery codes: only visible right after setting up or regenerating --}}
        @if ($isActive && $recoveryCodes)
            <x-ui.note tone="caution" :title="__('auth.two_factor.recovery_codes_title')">
                <p>{{ __('auth.two_factor.recovery_codes_warning') }}</p>
                <div class="mt-2 grid grid-cols-2 gap-1.5 rounded-md border border-line bg-surface p-3 font-mono text-sm text-fg select-all">
                    @foreach ($recoveryCodes as $code)
                        <span>{{ $code }}</span>
                    @endforeach
                </div>
            </x-ui.note>
        @endif

        {{-- Authenticator app setup --}}
        @if ($isActive && $showSetup && $secret && $qrUri && !$hasTotpEnabled)
            <div class="flex flex-col gap-4 rounded-lg border border-line bg-surface-sunken p-4">
                <div>
                    <p class="font-semibold text-fg">{{ __('auth.two_factor.setup_title') }}</p>
                    <p class="ui-help mt-1">{{ __('auth.two_factor.setup_instructions') }}</p>
                </div>

                <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-start">
                    {{-- QR code needs a light background, even in dark mode --}}
                    <div class="shrink-0 rounded-lg bg-white p-3">{!! \App\Helpers\QrEncoder::toSvg($qrUri) !!}</div>
                    <div class="min-w-0">
                        <p class="ui-label">{{ __('auth.two_factor.manual_entry') }}</p>
                        <code class="mt-1 block break-all rounded-md bg-surface px-3 py-1.5 font-mono text-sm text-fg select-all">{{ $secret }}</code>
                    </div>
                </div>

                <form method="POST" action="{{ route('two-factor.confirm-totp') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <x-ui.field name="code" id="totp_code" :label="__('auth.two_factor.enter_code')" class="w-40">
                        <x-ui.input id="totp_code" name="code" inputmode="numeric" maxlength="6" class="font-mono" required autofocus autocomplete="one-time-code" placeholder="000000" />
                    </x-ui.field>
                    <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('auth.two_factor.confirm_button') }}</x-ui.button>
                </form>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex flex-col gap-4 border-t border-line pt-4">
            @if (! $isActive)
                <form method="POST" action="{{ route('two-factor.toggle') }}">
                    @csrf
                    <x-ui.button variant="primary" size="md" icon="shield-check" type="submit">{{ __('auth.two_factor.enable_2fa') }}</x-ui.button>
                </form>
            @else
                @if ($hasTotpEnabled)
                    <form method="POST" action="{{ route('two-factor.regenerate-codes') }}" data-confirm="{{ __('auth.two_factor.regenerate_confirm') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-ui.field name="code" id="regen_code" :label="__('auth.two_factor.enter_code')" :help="__('auth.two_factor.remaining_codes', ['count' => $remainingCodes])" class="w-40">
                            <x-ui.input id="regen_code" name="code" inputmode="numeric" maxlength="6" class="font-mono" required autocomplete="one-time-code" placeholder="000000" />
                        </x-ui.field>
                        <x-ui.button size="md" icon="refresh-cw" type="submit">{{ __('auth.two_factor.regenerate_codes') }}</x-ui.button>
                    </form>

                    <form method="POST" action="{{ route('two-factor.disable-totp') }}" data-confirm="{{ __('auth.two_factor.disable_confirm') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-ui.field name="code" id="disable_code" :label="__('auth.two_factor.enter_code')" :help="__('auth.two_factor.disable_hint')" class="w-40">
                            <x-ui.input id="disable_code" name="code" inputmode="numeric" maxlength="6" class="font-mono" required autocomplete="one-time-code" placeholder="000000" />
                        </x-ui.field>
                        <x-ui.button variant="danger" size="md" icon="shield-off" type="submit">{{ __('auth.two_factor.disable_totp') }}</x-ui.button>
                    </form>
                @endif

                @if ($method === 'email')
                    <form id="send-code-form" method="POST" action="{{ route('two-factor.send-code') }}" class="hidden">@csrf</form>
                @endif

                {{-- Switch to the authenticator app: confirm with the email code --}}
                @if (! $hasTotpEnabled && ! $showSetup)
                    <form method="POST" action="{{ route('two-factor.enable-totp') }}" class="flex flex-wrap items-end gap-2">
                        @csrf
                        <x-ui.field name="code" id="enable_totp_code" :label="__('auth.two_factor.verify_code_email')" :help="__('auth.two_factor.upgrade_hint')" class="w-40">
                            <x-ui.input id="enable_totp_code" name="code" inputmode="numeric" maxlength="6" class="font-mono" required autocomplete="one-time-code" placeholder="000000" />
                        </x-ui.field>
                        <x-ui.button size="md" icon="mail" type="submit" form="send-code-form">{{ __('auth.two_factor.send_code') }}</x-ui.button>
                        <x-ui.button variant="primary" size="md" icon="smartphone" type="submit">{{ __('auth.two_factor.enable_totp') }}</x-ui.button>
                    </form>
                @endif

                {{-- Turn off 2FA for your own account: confirm with a code --}}
                <form method="POST" action="{{ route('two-factor.toggle') }}" data-confirm="{{ __('auth.two_factor.disable_2fa_confirm') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <x-ui.field name="code" id="toggle_code" :label="$method === 'totp' ? __('auth.two_factor.enter_code') : __('auth.two_factor.verify_code_email')" class="w-40">
                        <x-ui.input id="toggle_code" name="code" inputmode="numeric" maxlength="6" class="font-mono" required autocomplete="one-time-code" placeholder="000000" />
                    </x-ui.field>
                    @if ($method === 'email')
                        <x-ui.button size="md" icon="mail" type="submit" form="send-code-form">{{ __('auth.two_factor.send_code') }}</x-ui.button>
                    @endif
                    <x-ui.button variant="ghost" size="md" icon="shield-off" type="submit">{{ __('auth.two_factor.disable_2fa') }}</x-ui.button>
                </form>
            @endif
        </div>
    </div>
@endif
