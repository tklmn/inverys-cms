{{-- Credentials for "gmail"; visible when the card above is selected --}}
<x-ui.card class="provider-section" data-provider="gmail" :title="__('mail-providers::mail-providers.gmail_settings')" icon="at-sign" tone="violet">
    <div class="flex flex-col gap-4">
        <div class="ui-grid">
            <x-ui.field name="gmail_client_id" :label="__('mail-providers::mail-providers.gmail_client_id')">
                <x-ui.input id="gmail_client_id" name="gmail_client_id" type="text" :value="old('gmail_client_id', $s('gmail.client_id'))" />
            </x-ui.field>

            {{-- The stored secret is never output; leave empty = keep --}}
            <x-ui.field
                name="gmail_client_secret"
                :label="__('mail-providers::mail-providers.gmail_client_secret')"
                :help="$storedSecrets['gmail.client_secret'] ? __('mail-providers::mail-providers.secret_stored') : null"
            >
                <x-ui.input
                    id="gmail_client_secret"
                    name="gmail_client_secret"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="$storedSecrets['gmail.client_secret'] ? '••••••••' : null"
                />
            </x-ui.field>
        </div>

        @include('mail-providers::partials.oauth-status', ['provider' => 'gmail', 'providerLabel' => __('mail-providers::mail-providers.provider_gmail')])
    </div>
</x-ui.card>
