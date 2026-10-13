<x-app-layout>
    <x-slot name="header">{{ __('form-builder::forms.settings_title') }}</x-slot>

    @php
        $val = fn (string $key) => old($key, $settings['form_builder.'.$key] ?? $defaults[$key]);
        $def = fn (string $key) => $defaults[$key];
    @endphp

    <x-ui.page :title="__('form-builder::forms.settings_title')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.forms.index')">
                {{ __('form-builder::forms.forms') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="form-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="form-settings-form" action="{{ route('backend.forms.settings.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('form-builder::forms.notifications')" icon="bell">
                <div class="ui-grid">
                    <x-ui.setting-field
                        name="default_recipients"
                        :label="__('form-builder::forms.default_recipients')"
                        :help="__('form-builder::forms.default_recipients_hint')"
                        :value="$val('default_recipients')"
                        :default="$def('default_recipients')"
                        class="ui-grid-full"
                    >
                        <x-ui.input
                            id="default_recipients"
                            name="default_recipients"
                            data-field="default_recipients"
                            :value="$val('default_recipients')"
                            :placeholder="__('form-builder::forms.recipients_placeholder')"
                        />
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('form-builder::forms.sender')" icon="send" tone="teal">
                <div class="ui-grid">
                    <x-ui.setting-field
                        name="from_name"
                        :label="__('form-builder::forms.from_name')"
                        :help="! empty($mailFallbacks['from_name']) ? __('form-builder::forms.fallback_mail_settings', ['value' => $mailFallbacks['from_name']]) : null"
                        :value="$val('from_name')"
                        :default="$def('from_name')"
                    >
                        <x-ui.input
                            id="from_name"
                            name="from_name"
                            data-field="from_name"
                            :value="$val('from_name')"
                            placeholder="{{ $mailFallbacks['from_name'] ?? '' }}"
                        />
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="from_email"
                        :label="__('form-builder::forms.from_email')"
                        :help="! empty($mailFallbacks['from_email']) ? __('form-builder::forms.fallback_mail_settings', ['value' => $mailFallbacks['from_email']]) : null"
                        :value="$val('from_email')"
                        :default="$def('from_email')"
                    >
                        <x-ui.input
                            id="from_email"
                            name="from_email"
                            type="email"
                            data-field="from_email"
                            :value="$val('from_email')"
                            placeholder="{{ $mailFallbacks['from_email'] ?? '' }}"
                        />
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('form-builder::forms.rate_limiting')" icon="gauge" tone="caution">
                <div class="ui-grid">
                    <x-ui.setting-field
                        name="rate_limit"
                        :label="__('form-builder::forms.rate_limit')"
                        :help="__('form-builder::forms.rate_limit_hint')"
                        :value="$val('rate_limit')"
                        :default="$def('rate_limit')"
                    >
                        {{-- Unit next to the field, otherwise "5" has no context --}}
                        <div class="flex items-center gap-2">
                            <x-ui.input
                                id="rate_limit"
                                name="rate_limit"
                                type="number"
                                min="1"
                                max="100"
                                required
                                class="min-w-0 flex-1"
                                data-field="rate_limit"
                                :value="$val('rate_limit')"
                            />
                            <span class="shrink-0 text-sm text-fg-muted">{{ __('form-builder::forms.rate_limit_unit') }}</span>
                        </div>
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <x-ui.button variant="ghost" size="md" icon="rotate-ccw" id="reset-all-btn">
                    {{ __('form-builder::forms.reset_all_defaults') }}
                </x-ui.button>

                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
