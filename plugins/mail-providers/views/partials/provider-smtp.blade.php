{{-- Credentials for "smtp"; visible when the card above is selected --}}
<x-ui.card class="provider-section" data-provider="smtp" :title="__('mail-providers::mail-providers.smtp_settings')" icon="server">
    <div class="flex flex-col gap-4">
        {{-- Connection: where to --}}
        <div class="ui-grid">
            <x-ui.field name="smtp_host" :label="__('mail-providers::mail-providers.smtp_host')" :help="__('mail-providers::mail-providers.smtp_host_hint')">
                <x-ui.input id="smtp_host" name="smtp_host" icon="server" placeholder="smtp.example.org" :value="old('smtp_host', $s('smtp.host'))" /> {{-- i18n-ignore: example host --}}
            </x-ui.field>

            <x-ui.field name="smtp_port" :label="__('mail-providers::mail-providers.smtp_port')">
                <x-ui.input id="smtp_port" name="smtp_port" type="number" :value="old('smtp_port', $s('smtp.port'))" />
            </x-ui.field>

            <x-ui.field name="smtp_encryption" :label="__('mail-providers::mail-providers.smtp_encryption')">
                <x-ui.select id="smtp_encryption" name="smtp_encryption">
                    <option value="tls" @selected(old('smtp_encryption', $s('smtp.encryption')) === 'tls')>{{ __('mail-providers::mail-providers.smtp_encryption_tls') }}</option>
                    <option value="ssl" @selected(old('smtp_encryption', $s('smtp.encryption')) === 'ssl')>{{ __('mail-providers::mail-providers.smtp_encryption_ssl') }}</option>
                    <option value="" @selected(old('smtp_encryption', $s('smtp.encryption')) === '')>{{ __('mail-providers::mail-providers.smtp_encryption_none') }}</option>
                </x-ui.select>
            </x-ui.field>
        </div>

        {{-- Sign-in: who --}}
        <div class="ui-grid">
            <x-ui.field name="smtp_username" :label="__('mail-providers::mail-providers.smtp_username')">
                <x-ui.input id="smtp_username" name="smtp_username" icon="user-round" autocomplete="off" :value="old('smtp_username', $s('smtp.username'))" />
            </x-ui.field>

            {{-- The stored password is never output; leave empty = keep --}}
            <x-ui.field
                name="smtp_password"
                :label="__('mail-providers::mail-providers.smtp_password')"
                :help="$storedSecrets['smtp.password'] ? __('mail-providers::mail-providers.password_stored') : null"
            >
                <x-ui.input
                    id="smtp_password"
                    name="smtp_password"
                    type="password"
                    icon="key-round"
                    autocomplete="new-password"
                    :placeholder="$storedSecrets['smtp.password'] ? '••••••••' : null"
                />
            </x-ui.field>
        </div>
    </div>
</x-ui.card>
