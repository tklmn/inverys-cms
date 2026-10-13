{{-- Credentials for "microsoft"; visible when the card above is selected --}}
<x-ui.card class="provider-section" data-provider="microsoft" :title="__('mail-providers::mail-providers.microsoft_settings')" icon="building-2" tone="teal">
    <div class="flex flex-col gap-4">
        <div class="ui-grid">
            <x-ui.field
                name="microsoft_tenant_id"
                :label="__('mail-providers::mail-providers.microsoft_tenant_id')"
                :help="__('mail-providers::mail-providers.microsoft_tenant_id_hint')"
            >
                <x-ui.input id="microsoft_tenant_id" name="microsoft_tenant_id" type="text" :value="old('microsoft_tenant_id', $s('microsoft.tenant_id'))" />
            </x-ui.field>

            <x-ui.field name="microsoft_client_id" :label="__('mail-providers::mail-providers.microsoft_client_id')">
                <x-ui.input id="microsoft_client_id" name="microsoft_client_id" type="text" :value="old('microsoft_client_id', $s('microsoft.client_id'))" />
            </x-ui.field>

            {{-- The stored secret is never output; leave empty = keep --}}
            <x-ui.field
                name="microsoft_client_secret"
                :label="__('mail-providers::mail-providers.microsoft_client_secret')"
                :help="$storedSecrets['microsoft.client_secret'] ? __('mail-providers::mail-providers.secret_stored') : null"
                class="ui-grid-full"
            >
                <x-ui.input
                    id="microsoft_client_secret"
                    name="microsoft_client_secret"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="$storedSecrets['microsoft.client_secret'] ? '••••••••' : null"
                />
            </x-ui.field>
        </div>

        @include('mail-providers::partials.oauth-status', ['provider' => 'microsoft', 'providerLabel' => __('mail-providers::mail-providers.provider_microsoft')])
    </div>
</x-ui.card>
