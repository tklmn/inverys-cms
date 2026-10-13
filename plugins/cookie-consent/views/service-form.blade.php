<x-app-layout>
    <x-slot name="header">{{ $service ? __('cookie-consent::cc.edit_service') : __('cookie-consent::cc.create_service') }}</x-slot>

    <x-ui.page :title="$service ? __('cookie-consent::cc.edit_service') : __('cookie-consent::cc.create_service')">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="md" icon="arrow-left" :href="route('backend.cookie-consent.services')">
                {{ __('translation.back') }}
            </x-ui.button>
        </x-slot:actions>

        <form
            action="{{ $service ? route('backend.cookie-consent.services.update', $service['id']) : route('backend.cookie-consent.services.store') }}"
            method="POST"
            class="flex flex-col gap-4"
        >
            @csrf
            @if($service)
                @method('PUT')
            @endif

            <x-ui.card :title="__('cookie-consent::cc.service_details')" icon="cookie" tone="accent">
                <div class="flex flex-col gap-4">
                    <div class="ui-grid">
                        <x-ui.field name="name" :label="__('cookie-consent::cc.service_name')">
                            <x-ui.input name="name" id="name" maxlength="200" required :placeholder="__('cookie-consent::cc.service_name_placeholder')" :value="old('name', $service['name'] ?? '')" />
                        </x-ui.field>

                        <x-ui.field name="category" :label="__('cookie-consent::cc.service_category')">
                            <x-ui.select name="category" id="category" required>
                                @foreach(['functional', 'statistics', 'marketing'] as $cat)
                                    <option value="{{ $cat }}" @selected(old('category', $service['category'] ?? '') === $cat)>
                                        {{ __('cookie-consent::cc.category_' . $cat) }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field name="position" :label="__('cookie-consent::cc.service_position')">
                            <x-ui.select name="position" id="position" required>
                                @foreach(['head' => 'position_head', 'body_start' => 'position_body_start', 'body_end' => 'position_body_end'] as $val => $labelKey)
                                    <option value="{{ $val }}" @selected(old('position', $service['position'] ?? 'body_end') === $val)>
                                        {{ __('cookie-consent::cc.' . $labelKey) }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <x-ui.field name="description" :label="__('cookie-consent::cc.service_description')" optional>
                        <x-ui.textarea name="description" id="description" rows="2" maxlength="1000">{{ old('description', $service['description'] ?? '') }}</x-ui.textarea>
                    </x-ui.field>

                    <x-ui.field name="script" :label="__('cookie-consent::cc.service_script')" :help="__('cookie-consent::cc.service_script_hint')">
                        <x-ui.textarea name="script" id="script" rows="8" required class="font-mono text-xs">{{ old('script', $service['script'] ?? '') }}</x-ui.textarea>
                    </x-ui.field>

                    {{--
                        Allowed addresses: the website's strict security policy blocks
                        third-party sources — these are the ones the service loads from
                        or sends to. They only apply with consent.
                    --}}
                    @php
                        $presets = [
                            'ga4' => ['label' => 'Google Analytics 4', 'hosts' => "https://www.googletagmanager.com\nhttps://*.google-analytics.com\nhttps://*.analytics.google.com"],
                            'gtm' => ['label' => 'Google Tag Manager', 'hosts' => "https://www.googletagmanager.com\nhttps://*.google-analytics.com\nhttps://*.analytics.google.com"],
                            'matomo' => ['label' => 'Matomo Cloud', 'hosts' => "https://*.matomo.cloud\nhttps://cdn.matomo.cloud"],
                            'plausible' => ['label' => 'Plausible', 'hosts' => 'https://plausible.io'],
                        ];
                        $hostsValue = old('hosts', implode("\n", $service['hosts'] ?? []));
                    @endphp
                    <div class="ui-grid">
                        <x-ui.field name="hosts" :label="__('cookie-consent::cc.service_hosts')" :help="__('cookie-consent::cc.service_hosts_hint')" optional>
                            <x-ui.textarea name="hosts" id="hosts" rows="4" class="font-mono text-xs" placeholder="https://www.googletagmanager.com">{{ $hostsValue }}</x-ui.textarea> {{-- i18n-ignore: example address --}}
                        </x-ui.field>
                        <x-ui.field name="hosts_preset" :label="__('cookie-consent::cc.service_hosts_preset')" :help="__('cookie-consent::cc.service_hosts_preset_hint')" optional>
                            <x-ui.select id="hosts_preset" data-hosts-preset>
                                <option value="">{{ __('cookie-consent::cc.service_hosts_preset_none') }}</option>
                                @foreach($presets as $key => $preset)
                                    <option value="{{ $preset['hosts'] }}">{{ $preset['label'] }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                    </div>
                    <script @cspNonce>
                        document.querySelector('[data-hosts-preset]')?.addEventListener('change', function () {
                            if (this.value) document.getElementById('hosts').value = this.value;
                        });
                    </script>
                </div>

                <x-slot:foot>
                    <div class="flex justify-end">
                        <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                    </div>
                </x-slot:foot>
            </x-ui.card>
        </form>
    </x-ui.page>
</x-app-layout>
