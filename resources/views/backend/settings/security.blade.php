<x-app-layout>
    <x-slot name="header">{{ __('translation.settings.security_title') }}</x-slot>

    <x-ui.page :title="__('translation.settings.security_title')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="security-settings">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        {{-- Two columns from xl as in SEO: each column on its own, without gaps between cards of unequal height --}}
        <form id="security-settings" method="POST" action="{{ route('backend.settings.security.save') }}" class="grid grid-cols-[minmax(0,1fr)] items-start gap-4 xl:grid-cols-2">
            @csrf

            <div class="flex flex-col gap-4">
                <x-ui.card :title="__('translation.settings.security_password_policy')" icon="lock" tone="accent">
                    <div class="flex flex-col gap-4">
                        <x-ui.field
                            name="password_min_length"
                            :label="__('translation.settings.security_password_min_length')"
                            :help="__('translation.settings.security_password_min_length_hint')"
                        >
                            <x-ui.input id="password_min_length" name="password_min_length" type="number" min="8" max="128" :value="old('password_min_length', $securitySettings['password_min_length'])" />
                        </x-ui.field>

                        <div class="ui-choices">
                            <x-ui.switch-row
                                name="password_require_uppercase"
                                :label="__('translation.settings.security_password_require_uppercase')"
                                :checked="$securitySettings['password_require_uppercase'] === 'on'"
                            />
                            <x-ui.switch-row
                                name="password_require_numbers"
                                :label="__('translation.settings.security_password_require_numbers')"
                                :checked="$securitySettings['password_require_numbers'] === 'on'"
                            />
                            <x-ui.switch-row
                                name="password_require_symbols"
                                :label="__('translation.settings.security_password_require_symbols')"
                                :checked="$securitySettings['password_require_symbols'] === 'on'"
                            />
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('translation.settings.security_two_factor')" icon="shield-check" tone="positive">
                    <div class="flex flex-col gap-4">
                        <div>
                            <x-ui.switch-row
                                name="two_factor_enabled"
                                :label="__('translation.settings.security_two_factor_enforce')"
                                :hint="__('translation.settings.security_two_factor_enabled_hint')"
                                :checked="$securitySettings['two_factor_enabled'] === 'on'"
                            />
                        </div>

                        {{-- Only useful while two-factor login is on --}}
                        <div data-requires="two_factor_enabled">
                            <x-ui.field
                                name="two_factor_code_lifetime"
                                :label="__('translation.settings.security_two_factor_code_lifetime')"
                                :help="__('translation.settings.security_two_factor_code_lifetime_hint')"
                            >
                                <x-ui.input id="two_factor_code_lifetime" name="two_factor_code_lifetime" type="number" min="5" max="30" :value="old('two_factor_code_lifetime', $securitySettings['two_factor_code_lifetime'])" />
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('translation.settings.security_login_protection')" icon="shield-alert" tone="caution">
                    <x-ui.switch-row
                        class="mb-4"
                        name="registration_enabled"
                        :label="__('translation.settings.security_registration_enabled')"
                        :hint="__('translation.settings.security_registration_enabled_hint')"
                        :checked="$securitySettings['registration_enabled'] !== 'off'"
                    />
                    <div class="ui-grid">
                        <x-ui.field
                            name="login_max_attempts"
                            :label="__('translation.settings.security_login_max_attempts')"
                            :help="__('translation.settings.security_login_max_attempts_hint')"
                        >
                            <x-ui.input id="login_max_attempts" name="login_max_attempts" type="number" min="3" max="20" :value="old('login_max_attempts', $securitySettings['login_max_attempts'])" />
                        </x-ui.field>

                        <x-ui.field
                            name="login_lockout_duration"
                            :label="__('translation.settings.security_login_lockout_duration')"
                            :help="__('translation.settings.security_login_lockout_duration_hint')"
                        >
                            <x-ui.input id="login_lockout_duration" name="login_lockout_duration" type="number" min="1" max="60" :value="old('login_lockout_duration', $securitySettings['login_lockout_duration'])" />
                        </x-ui.field>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('translation.settings.security_data_retention')" icon="database" tone="teal">
                    <x-ui.field
                        name="notification_retention_days"
                        :label="__('translation.settings.security_notification_retention')"
                        :help="__('translation.settings.security_notification_retention_hint')"
                    >
                        <x-ui.input id="notification_retention_days" name="notification_retention_days" type="number" min="7" max="365" :value="old('notification_retention_days', $securitySettings['notification_retention_days'])" />
                    </x-ui.field>
                </x-ui.card>
            </div>

            <div class="flex flex-col gap-4">
                <x-ui.card :title="__('translation.settings.security_rate_limiting')" icon="clock" tone="violet">
                    <div class="ui-grid">
                        <x-ui.field
                            name="api_rate_limit"
                            :label="__('translation.settings.security_api_rate_limit')"
                            :help="__('translation.settings.security_api_rate_limit_hint')"
                        >
                            <x-ui.input id="api_rate_limit" name="api_rate_limit" type="number" min="10" max="1000" :value="old('api_rate_limit', $securitySettings['api_rate_limit'])" />
                        </x-ui.field>

                        <x-ui.field
                            name="session_lifetime"
                            :label="__('translation.settings.security_session_lifetime')"
                            :help="__('translation.settings.security_session_lifetime_hint')"
                        >
                            <x-ui.input id="session_lifetime" name="session_lifetime" type="number" min="5" max="1440" :value="old('session_lifetime', $securitySettings['session_lifetime'])" />
                        </x-ui.field>
                    </div>
                </x-ui.card>

                {{--
                    security.txt (RFC 9116): where security vulnerabilities of this website
                    are reported. Empty by default — then there is no file (404).
                --}}
                <x-ui.card :title="__('translation.settings.security_txt')" icon="shield-alert" tone="critical">
                    <x-slot:head>
                        <x-ui.button
                            variant="ghost"
                            icon="external-link"
                            :href="route('security-txt')"
                            target="_blank"
                            rel="noopener noreferrer"
                            :disabled="$securityTxt === null"
                            :disabled-reason="__('translation.settings.security_txt_off')"
                        >{{ __('translation.seo.open') }}</x-ui.button>
                    </x-slot:head>

                    @php
                        // Fallback from the .env (config/cms.php): applies while the field here is empty
                        $txtEnv = array_map(fn ($value) => trim((string) $value), (array) config('cms.security_txt', []));
                        $envHint = fn (string $key) => ($txtEnv[$key] ?? '') !== ''
                            ? ' '.__('translation.settings.security_txt_env_fallback', ['value' => $txtEnv[$key]])
                            : '';
                    @endphp

                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-4">
                            <p class="ui-help">{{ __('translation.settings.security_txt_lead') }}</p>

                            <x-ui.field name="txt_contact" :label="__('translation.settings.security_txt_contact')" :help="__('translation.settings.security_txt_contact_hint').$envHint('contact')" optional>
                                <x-ui.textarea id="txt_contact" name="txt_contact" rows="3" class="font-mono" spellcheck="false" :placeholder="'security@example.com'.PHP_EOL.'https://example.com/security'">{{ old('txt_contact', $securitySettings['txt_contact']) }}</x-ui.textarea>
                            </x-ui.field>

                            <div class="ui-grid">
                                <x-ui.field name="txt_policy" :label="__('translation.settings.security_txt_policy')" :help="__('translation.settings.security_txt_https_hint').$envHint('policy')" optional>
                                    <x-ui.input id="txt_policy" name="txt_policy" type="url" :placeholder="'https://…'" :value="old('txt_policy', $securitySettings['txt_policy'])" />
                                </x-ui.field>
                                <x-ui.field name="txt_encryption" :label="__('translation.settings.security_txt_encryption')" :help="__('translation.settings.security_txt_encryption_hint').$envHint('encryption')" optional>
                                    <x-ui.input id="txt_encryption" name="txt_encryption" type="url" :placeholder="'https://…/pgp-key.txt'" :value="old('txt_encryption', $securitySettings['txt_encryption'])" />
                                </x-ui.field>
                            </div>

                            <div class="ui-grid">
                                <x-ui.field name="txt_languages" :label="__('translation.settings.security_txt_languages')" :help="__('translation.settings.security_txt_languages_hint').$envHint('preferred_languages')" optional>
                                    <x-ui.input id="txt_languages" name="txt_languages" :placeholder="'de, en'" :value="old('txt_languages', $securitySettings['txt_languages'])" />
                                </x-ui.field>
                                {{-- A fixed expiry date in the .env takes precedence over the select — then locked with a reason --}}
                                <x-ui.field
                                    name="txt_expires_days"
                                    :label="__('translation.settings.security_txt_expires')"
                                    :help="($txtEnv['expires'] ?? '') !== ''
                                        ? __('translation.settings.security_txt_expires_env', ['date' => $txtEnv['expires']])
                                        : __('translation.settings.security_txt_expires_hint')"
                                >
                                    <x-ui.select id="txt_expires_days" name="txt_expires_days" :disabled="($txtEnv['expires'] ?? '') !== ''">
                                        @foreach(\App\Support\SecurityTxt::EXPIRES_OPTIONS as $days)
                                            <option value="{{ $days }}" @selected((int) old('txt_expires_days', $securitySettings['txt_expires_days']) === $days)>{{ trans_choice('translation.settings.security_txt_days', $days, ['count' => $days]) }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                            </div>
                        </div>

                        <div>
                            <p class="ui-label mb-2">{{ __('translation.settings.security_txt_preview') }}</p>
                            @if($securityTxt !== null)
                                <pre class="overflow-x-auto rounded-lg border border-line bg-surface-sunken p-3 font-mono text-xs whitespace-pre-wrap text-fg">{{ $securityTxt }}</pre>
                            @else
                                <x-ui.empty icon="shield-off">{{ __('translation.settings.security_txt_off') }}</x-ui.empty>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
