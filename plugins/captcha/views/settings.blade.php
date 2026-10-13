<x-app-layout>
    <x-slot name="header">{{ __('captcha::captcha.settings') }}</x-slot>

    @php
        $s = fn ($k) => $settings['captcha.'.$k] ?? $defaults[$k];
        $savedProvider = $s('provider');
        $provider = old('provider', $savedProvider);

        // Icon and tone per provider slug — the same in the choice on the right and in the key cards
        $providerMeta = [
            'math' => ['icon' => 'hash', 'tone' => 'accent'],
            'recaptcha-v3' => ['icon' => 'gauge', 'tone' => 'caution'],
            'turnstile' => ['icon' => 'cloud', 'tone' => 'teal'],
            'hcaptcha' => ['icon' => 'images', 'tone' => 'violet'],
            'captchafox' => ['icon' => 'puzzle', 'tone' => 'accent'],
            'friendly-captcha' => ['icon' => 'cpu', 'tone' => 'positive'],
        ];

        // Providers with a key pair: settings prefix => provider slug
        $keyProviders = [
            'recaptcha_v3' => 'recaptcha-v3',
            'turnstile' => 'turnstile',
            'hcaptcha' => 'hcaptcha',
            'captchafox' => 'captchafox',
            'friendly_captcha' => 'friendly-captcha',
        ];
    @endphp

    <x-ui.page :title="__('captcha::captcha.settings')" :lead="__('captcha::captcha.page_lead')">
        <x-slot:actions>
            {{-- Reached via the plugin list; without that permission the link leads nowhere --}}
            @permission('manage-plugins')
                <x-ui.button size="md" icon="arrow-left" :href="route('backend.plugins.index')">
                    {{ __('translation.navigation.plugins') }}
                </x-ui.button>
            @endpermission
            {{-- Save in the header: with five key cards the button at the end needs a long scroll --}}
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="captcha-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        {{-- Switch and keys on the left, default provider on the right (narrow: below) --}}
        <div class="ui-split">
            <form id="captcha-settings-form" action="{{ route('backend.captcha.settings.update') }}" method="POST" class="ui-split-main">
                @csrf
                @method('PUT')

                <x-ui.card :title="__('captcha::captcha.general')" icon="shield">
                    <div class="flex flex-col gap-4">
                        <x-ui.switch-row
                            name="enabled"
                            :label="__('captcha::captcha.enabled')"
                            :hint="__('captcha::captcha.enabled_hint')"
                            :checked="(bool) (old('enabled', $s('enabled')) == '1')"
                            on-value="1"
                            off-value="0"
                            input-id="enabled"
                        />

                        {{-- Only the math challenge has a difficulty level; the provider radios sit in the aside (form=) --}}
                        <div class="ui-grid" data-requires="enabled">
                            <x-ui.field
                                name="math_difficulty"
                                :label="__('captcha::captcha.math_difficulty')"
                                :help="__('captcha::captcha.math_difficulty_hint')"
                                data-requires="provider=math"
                            >
                                <x-ui.select id="math_difficulty" name="math_difficulty">
                                    @foreach(['easy', 'medium', 'hard'] as $level)
                                        <option value="{{ $level }}" @selected(old('math_difficulty', $s('math_difficulty')) === $level)>{{ __('captcha::captcha.difficulty_'.$level) }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.card>

                {{--
                    Keys per provider, all cards with the same structure. Not tied to the
                    default on the right: a captcha field in the form builder can choose a
                    different provider and then needs that provider's keys.
                    Stored secret keys are never output — empty = keep.
                --}}
                @foreach($keyProviders as $prefix => $slug)
                    @php
                        $hasSecret = filled($s($prefix.'_secret_key'));
                        $hasSite = filled($s($prefix.'_site_key'));
                        [$keyTone, $keyLabel] = match (true) {
                            $hasSite && $hasSecret => ['positive', 'captcha::captcha.keys_stored'],
                            $hasSite || $hasSecret => ['caution', 'captcha::captcha.keys_incomplete'],
                            default => ['neutral', 'captcha::captcha.keys_missing'],
                        };
                    @endphp

                    <x-ui.card
                        :title="__('captcha::captcha.provider_'.$prefix)"
                        :icon="$providerMeta[$slug]['icon']"
                        :tone="$providerMeta[$slug]['tone']"
                        data-requires="enabled"
                    >
                        <x-slot:head>
                            @if($savedProvider === $slug)
                                <x-ui.badge tone="accent">{{ __('captcha::captcha.default_badge') }}</x-ui.badge>
                            @endif
                            <x-ui.badge :tone="$keyTone">{{ __($keyLabel) }}</x-ui.badge>
                        </x-slot:head>

                        <div class="ui-grid">
                            <x-ui.field :name="$prefix.'_site_key'" :label="__('captcha::captcha.site_key')">
                                <x-ui.input :id="$prefix.'_site_key'" :name="$prefix.'_site_key'" :value="old($prefix.'_site_key', $s($prefix.'_site_key'))" autocomplete="off" />
                            </x-ui.field>

                            <x-ui.field
                                :name="$prefix.'_secret_key'"
                                :label="__('captcha::captcha.secret_key')"
                                :help="$hasSecret ? __('captcha::captcha.secret_stored') : null"
                            >
                                <x-ui.input
                                    :id="$prefix.'_secret_key'"
                                    :name="$prefix.'_secret_key'"
                                    type="password"
                                    autocomplete="new-password"
                                    :placeholder="$hasSecret ? '••••••••' : null"
                                />
                            </x-ui.field>

                            @if($prefix === 'recaptcha_v3')
                                <x-ui.field name="recaptcha_v3_threshold" class="ui-grid-full" :label="__('captcha::captcha.threshold')" :help="__('captcha::captcha.threshold_hint')">
                                    <x-ui.input id="recaptcha_v3_threshold" name="recaptcha_v3_threshold" type="number" :value="old('recaptcha_v3_threshold', $s('recaptcha_v3_threshold'))" step="0.1" min="0" max="1" />
                                </x-ui.field>
                            @endif
                        </div>
                    </x-ui.card>
                @endforeach

                {{-- Saves switch, default provider and keys together — hence below all cards --}}
                <div class="flex justify-end">
                    <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                </div>
            </form>

            {{-- Default provider on the right; the selection belongs to the form on the left via form= --}}
            <div class="ui-split-aside">
                <x-ui.card :title="__('captcha::captcha.default_provider')" icon="shield-check" data-requires="enabled">
                    <div class="flex flex-col gap-3">
                        <p class="ui-help">{{ __('captcha::captcha.provider_hint') }}</p>

                        @foreach(\Plugins\Captcha\Services\CaptchaService::availableProviders() as $slug => $labelKey)
                            <x-ui.option-card
                                name="provider"
                                form="captcha-settings-form"
                                :value="$slug"
                                :title="__($labelKey)"
                                :description="__($labelKey.'_desc')"
                                :icon="$providerMeta[$slug]['icon'] ?? 'shield'"
                                :tone="$providerMeta[$slug]['tone'] ?? 'accent'"
                                :checked="$provider === $slug"
                                :badge="$savedProvider === $slug ? __('captcha::captcha.default_badge') : null"
                            />
                        @endforeach

                        <p class="ui-help">{{ __('captcha::captcha.keys_hint') }}</p>
                    </div>
                </x-ui.card>
            </div>
        </div>
    </x-ui.page>
</x-app-layout>
