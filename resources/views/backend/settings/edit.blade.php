<x-app-layout>
    <x-slot name="header">{{ __('translation.settings.edit') }}</x-slot>

    @php
        // Input type, options and hint come from App\Support\GeneralSettings
        $title = $definition['label'];
        $darkModeWarning = $setting->key === 'theme' ? \App\Support\GeneralSettings::darkModeWarning() : null;
    @endphp

    <x-ui.page :title="$title" :lead="$setting->key">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.settings.index')">{{ __('translation.settings.title') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="setting-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="setting-form" method="POST" action="{{ route('backend.settings.update', $setting) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('translation.settings.value')" :icon="$definition['icon']" tone="accent">
                <x-ui.field name="value" :label="$title" :help="$definition['help']" :optional="$setting->key === 'date_format'">
                    @if($options !== null)
                        <x-ui.select id="value" name="value" required autofocus>
                            @foreach($options as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected((string) old('value', $setting->value) === (string) $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                        </x-ui.select>
                    @elseif($definition['input'] === 'number')
                        <x-ui.input id="value" name="value" type="number" min="0" max="1000" :value="old('value', $setting->value)" required autofocus />
                    @else
                        <x-ui.input
                            id="value"
                            name="value"
                            :value="old('value', $setting->value)"
                            :required="$setting->key !== 'date_format'"
                            :class="in_array($setting->key, ['date_format', 'favicon'], true) ? 'font-mono' : null"
                            autofocus
                        />
                    @endif
                </x-ui.field>

                @if($darkModeWarning)
                    <x-ui.note tone="caution" class="mt-4">{{ $darkModeWarning }}</x-ui.note>
                @endif

                <x-ui.facts class="mt-4 border-t border-line pt-4">
                    <x-ui.fact :label="__('translation.settings.key')"><code class="font-mono text-xs">{{ $setting->key }}</code></x-ui.fact>
                    <x-ui.fact :label="__('translation.updated_at')">{{ format_datetime($setting->updated_at) }}</x-ui.fact>
                </x-ui.facts>
            </x-ui.card>

            @if($definition['user_override'])
                {{-- Precedence: profile over the website default --}}
                <x-ui.note tone="accent" icon="user-round" :title="__('translation.settings.user_override_title')">
                    {{ __('translation.settings.user_override_text') }}
                </x-ui.note>
            @endif
        </form>
    </x-ui.page>
</x-app-layout>
