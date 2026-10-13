<x-app-layout>
    <x-slot name="header">{{ __('cookie-consent::cc.settings_title') }}</x-slot>

    @php
        $colors = [
            'bg_color' => ['label' => 'cookie-consent::cc.bg_color', 'default' => '#1e293b'],
            'text_color' => ['label' => 'cookie-consent::cc.text_color', 'default' => '#f1f5f9'],
            'btn_bg_color' => ['label' => 'cookie-consent::cc.btn_bg_color', 'default' => '#4f46e5'],
            'btn_text_color' => ['label' => 'cookie-consent::cc.btn_text_color', 'default' => '#ffffff'],
        ];
    @endphp

    <x-ui.page :title="__('cookie-consent::cc.settings_heading')">
        <x-slot:actions>
            <x-ui.button variant="secondary" size="md" icon="settings" :href="route('backend.cookie-consent.services')">
                {{ __('cookie-consent::cc.manage_services') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="cc-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="cc-settings-form" action="{{ route('backend.cookie-consent.settings.save') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-ui.card :title="__('cookie-consent::cc.settings_heading')" icon="cookie" tone="accent">
                <div class="flex flex-col gap-4">
                    <x-ui.switch-row
                        name="enabled"
                        :label="__('cookie-consent::cc.enable')"
                        :checked="($cc['enabled'] ?? '1') === '1'"
                        on-value="1"
                        off-value="0"
                    />

                    <x-ui.setting-field
                        name="banner_text"
                        :label="__('cookie-consent::cc.banner_text')"
                        :value="old('banner_text', $cc['banner_text'] ?? '')"
                        default=""
                    >
                        <x-ui.textarea
                            name="banner_text"
                            id="banner_text"
                            rows="3"
                            maxlength="1000"
                            data-field="banner_text"
                            :placeholder="__('cookie-consent::cc.banner_text_placeholder')"
                        >{{ old('banner_text', $cc['banner_text'] ?? '') }}</x-ui.textarea>
                    </x-ui.setting-field>

                    <div class="ui-grid">
                        <x-ui.field name="privacy_page_slug" :label="__('cookie-consent::cc.privacy_page')">
                            <x-ui.select name="privacy_page_slug" id="privacy_page_slug">
                                <option value="">{{ __('cookie-consent::cc.select_page') }}</option>
                                @foreach($pages as $slug => $title)
                                    <option value="{{ $slug }}" @selected(($cc['privacy_page_slug'] ?? '') === $slug)>{{ $title }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field name="imprint_page_slug" :label="__('cookie-consent::cc.imprint_page')">
                            <x-ui.select name="imprint_page_slug" id="imprint_page_slug">
                                <option value="">{{ __('cookie-consent::cc.select_page') }}</option>
                                @foreach($pages as $slug => $title)
                                    <option value="{{ $slug }}" @selected(($cc['imprint_page_slug'] ?? '') === $slug)>{{ $title }}</option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field name="position" :label="__('cookie-consent::cc.position')">
                            <x-ui.select name="position" id="position">
                                <option value="bottom" @selected(($cc['position'] ?? 'bottom') === 'bottom')>{{ __('cookie-consent::cc.position_bottom') }}</option>
                                <option value="modal" @selected(($cc['position'] ?? '') === 'modal')>{{ __('cookie-consent::cc.position_modal') }}</option>
                                <option value="corner" @selected(($cc['position'] ?? '') === 'corner')>{{ __('cookie-consent::cc.position_corner') }}</option>
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field
                            name="cookie_lifetime"
                            :label="__('cookie-consent::cc.cookie_lifetime')"
                            :help="__('cookie-consent::cc.cookie_lifetime_help')"
                        >
                            <x-ui.input type="number" name="cookie_lifetime" id="cookie_lifetime" min="1" max="3650" :value="old('cookie_lifetime', $cc['cookie_lifetime'] ?? '365')" />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('cookie-consent::cc.colors')" icon="palette" tone="violet">
                <div class="ui-grid">
                    @foreach($colors as $key => $meta)
                        <x-ui.field :name="$key" :label="__($meta['label'])">
                            <x-ui.color :name="$key" :value="old($key, $cc[$key] ?? $meta['default'])" />
                        </x-ui.field>
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card :title="__('cookie-consent::cc.reopen_heading')" icon="rotate-ccw" tone="teal">
                <div class="flex flex-col gap-4">
                    <x-ui.switch-row
                        name="show_reopen_btn"
                        :label="__('cookie-consent::cc.show_reopen_btn')"
                        :checked="($cc['show_reopen_btn'] ?? '1') === '1'"
                        on-value="1"
                        off-value="0"
                    />

                    <x-ui.field name="reopen_position" :label="__('cookie-consent::cc.reopen_position')">
                        <x-ui.select name="reopen_position" id="reopen_position">
                            <option value="left" @selected(($cc['reopen_position'] ?? 'left') === 'left')>{{ __('cookie-consent::cc.reopen_position_left') }}</option>
                            <option value="right" @selected(($cc['reopen_position'] ?? 'left') === 'right')>{{ __('cookie-consent::cc.reopen_position_right') }}</option>
                        </x-ui.select>
                    </x-ui.field>
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
