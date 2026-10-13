<x-app-layout>
    <x-slot name="header">{{ __('mail-providers::mail-providers.settings') }}</x-slot>

    @php
        $s = fn ($k) => $settings['mailproviders.'.$k] ?? $defaults[$k];
        $saved = $s('active_provider');
        $current = old('active_provider', $saved);

        // Three ways: any mail server via SMTP or a mailbox via sign-in (OAuth)
        $providers = [
            'smtp' => ['icon' => 'server', 'tone' => 'accent'],
            'microsoft' => ['icon' => 'building-2', 'tone' => 'teal'],
            'gmail' => ['icon' => 'at-sign', 'tone' => 'violet'],
        ];
    @endphp

    <x-ui.page :title="__('mail-providers::mail-providers.settings')" :lead="__('mail-providers::mail-providers.page_lead')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="mail-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        {{-- Credentials and sender on the left, sending method and test mail on the right (narrow: below) --}}
        <div class="ui-split">
        <form action="{{ route('backend.mail-providers.settings.update') }}" method="POST" class="ui-split-main" id="mail-settings-form">
            @csrf
            @method('PUT')


            {{-- Credentials — only the card of the selected provider is visible --}}
            @include('mail-providers::partials.provider-smtp')
            @include('mail-providers::partials.provider-microsoft-graph')
            @include('mail-providers::partials.provider-gmail')

            <x-ui.card :title="__('mail-providers::mail-providers.sender_heading')" icon="user-round" tone="caution">
                <div class="ui-grid">
                    <x-ui.field name="from_address" :label="__('mail-providers::mail-providers.from_address')" :help="__('mail-providers::mail-providers.from_address_hint')">
                        <x-ui.input id="from_address" name="from_address" type="email" icon="at-sign" :value="old('from_address', $s('from_address'))" />
                    </x-ui.field>

                    <x-ui.field name="from_name" :label="__('mail-providers::mail-providers.from_name')" :help="__('mail-providers::mail-providers.from_name_hint')">
                        <x-ui.input id="from_name" name="from_name" icon="user-round" :value="old('from_name', $s('from_name'))" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            {{-- Saves provider, credentials and sender together — hence below all cards --}}
            <div class="flex justify-end">
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>

        {{-- Sending method and test on the right; the selection belongs to the form on the left via form= --}}
        <div class="ui-split-aside">
                <x-ui.card :title="__('mail-providers::mail-providers.active_provider')" icon="send">
                    <div class="flex flex-col gap-3">
                        @foreach($providers as $key => $meta)
                            <x-ui.option-card
                                name="active_provider"
                                form="mail-settings-form"
                                :value="$key"
                                :title="__('mail-providers::mail-providers.provider_'.$key)"
                                :description="__('mail-providers::mail-providers.provider_'.$key.'_desc')"
                                :icon="$meta['icon']"
                                :tone="$meta['tone']"
                                :checked="$current === $key"
                                :badge="$saved === $key ? __('mail-providers::mail-providers.active_badge') : null"
                            />
                        @endforeach
                    </div>
                </x-ui.card>
        <x-ui.card :title="__('mail-providers::mail-providers.test_section')" icon="mail-open">
            <form action="{{ route('backend.mail-providers.test') }}" method="POST" class="flex flex-col gap-3">
                @csrf
                <p class="ui-help">{{ __('mail-providers::mail-providers.test_hint') }}</p>

                {{-- Stacked in the narrow column, otherwise the address gets cut to a stub --}}
                <div class="flex flex-col gap-2">
                    <x-ui.field name="test_email" class="min-w-0">
                        <x-ui.input
                            id="test_email"
                            name="test_email"
                            type="email"
                            icon="mail"
                            required
                            :value="old('test_email', auth()->user()->email)"
                            :aria-label="__('mail-providers::mail-providers.test_email')"
                        />
                    </x-ui.field>

                    <x-ui.button variant="secondary" size="md" icon="send" type="submit" class="self-start">
                        {{ __('mail-providers::mail-providers.test_send') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
        </div>
        </div>

        {{-- OAuth forms outside the main form (no nested forms) --}}
        @stack('oauth-forms')
    </x-ui.page>

    @push('scripts')
    <script @cspNonce>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('mail-settings-form');
        var sections = document.querySelectorAll('.provider-section');

        function show() {
            var picked = document.querySelector('input[name="active_provider"]:checked');
            var active = picked ? picked.value : '';
            sections.forEach(function (section) {
                section.classList.toggle('hidden', section.dataset.provider !== active);
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.name === 'active_provider') show();
        });
        show();
    });
    </script>
    @endpush
</x-app-layout>
