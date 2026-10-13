<x-app-layout>
    <x-slot name="header">{{ __('Customize') }}: {{ __($theme['name']) }}</x-slot>

    @php
        // Multilingual: original or translation of the texts
        $editLocale = \App\Support\EditLocale::current();

        // Settings by group — each group becomes its own card instead of
        // everything stacked in one long card
        $groups = [];
        foreach ($settings ?? [] as $key => $schema) {
            $groups[$schema['group'] ?? 'General'][$key] = $schema;
        }
    @endphp

    <x-ui.page :title="__('Customize').': '.__($theme['name'])" :lead="__('translation.settings.where.customize_lead')">
        <x-slot:actions>
            <x-ui.locale-switch />
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.themes.index')">{{ __('Themes') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="theme-customize-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="theme-customize-form" action="{{ route('backend.themes.save-customization', $theme['slug']) }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @if($editLocale)
                <input type="hidden" name="_lang" value="{{ $editLocale }}">
            @endif

            <x-ui.translation-note :form="new \App\Support\TranslationForm(null, $editLocale)" />

            @php
                // Groups of the theme.json → icon and colour; unknown groups (other themes) neutral
                $groupLook = [
                    'Colors' => ['palette', 'accent'], 'Branding' => ['image', 'violet'], 'Design' => ['sparkles', 'teal'],
                    'Layout' => ['layout-dashboard', 'neutral'], 'Navigation' => ['panel-top', 'accent'], 'Footer' => ['rows-3', 'violet'],
                    'Typography' => ['type', 'teal'], 'General' => ['sliders-horizontal', 'neutral'],
                ];
            @endphp

            @foreach($groups as $groupName => $fields)
                @php
                    [$groupIcon, $groupTone] = $groupLook[$groupName] ?? ['sliders-horizontal', 'neutral'];
                    // Yes/no settings as switch rows below the other fields
                    $switches = array_filter($fields, fn ($schema) => ($schema['type'] ?? 'text') === 'boolean');
                    $inputs = array_diff_key($fields, $switches);
                @endphp
                <x-ui.card :title="__($groupName)" :icon="$groupIcon" :tone="$groupTone">
                    @if($inputs)
                    <div class="ui-grid">
                        @foreach($inputs as $key => $schema)
                            @php
                                $value = $savedSettings[$key] ?? ($schema['default'] ?? '');
                                $default = (string) ($schema['default'] ?? '');
                                $type = $schema['type'] ?? 'text';
                                $inputName = 'settings['.$key.']';
                                // Long inputs span the full row
                                $wide = in_array($type, ['textarea', 'image'], true);

                                // Translation: only texts are translatable, everything else is locked
                                $isText = in_array($type, \App\Support\ThemeTranslations::TEXT_TYPES, true);
                                $locked = $editLocale && ! $isText;
                                $original = $value;
                                if ($editLocale && $isText) {
                                    $value = \App\Support\ThemeTranslations::translated('theme.'.$theme['slug'].'.'.$key, $editLocale) ?? '';
                                }
                            @endphp

                            <x-ui.setting-field
                                :name="$key"
                                :label="__($schema['label'] ?? $key)"
                                :help="isset($schema['description']) ? __($schema['description']) : null"
                                :value="(string) $value"
                                :default="$editLocale ? null : $default"
                                @class(['ui-grid-full' => $wide])
                            >
                                @switch($type)
                                    @case('color')
                                        <x-ui.color :name="$inputName" :value="$value" :field="$key" :class="$locked ? 'pointer-events-none opacity-55' : ''" />
                                        @break

                                    @case('image')
                                        {{-- Preview the dark mode logo on a dark background --}}
                                        <x-ui.image-field
                                            :id="$key"
                                            :name="$inputName"
                                            :value="$value"
                                            picker="theme-media-picker"
                                            :dark="str_ends_with($key, '_dark')"
                                            :disabled="$locked"
                                            :data-field="$key"
                                        />
                                        @break

                                    @case('select')
                                        <x-ui.select :id="$key" :name="$inputName" :data-field="$key" :disabled="$locked">
                                            @foreach(($schema['options'] ?? []) as $optVal => $optLabel)
                                                <option value="{{ $optVal }}" @selected((string) $value === (string) $optVal)>{{ __($optLabel) }}</option>
                                            @endforeach
                                        </x-ui.select>
                                        @break

                                    @case('textarea')
                                        <x-ui.textarea :id="$key" :name="$inputName" rows="3" :data-field="$key" :placeholder="$editLocale ? $original : null">{{ $value }}</x-ui.textarea>
                                        @break

                                    @default
                                        <x-ui.input :id="$key" :name="$inputName" :value="$value" :data-field="$key" :placeholder="$editLocale ? $original : null" />
                                @endswitch
                            </x-ui.setting-field>
                        @endforeach
                    </div>
                    @endif

                    @if($switches)
                        <div @class(['ui-choices', 'mt-4 border-t border-line pt-3' => $inputs])>
                            @foreach($switches as $key => $schema)
                                @php
                                    $value = (bool) ($savedSettings[$key] ?? ($schema['default'] ?? false));
                                    $default = (bool) ($schema['default'] ?? false) ? '1' : '0';
                                @endphp
                                <x-ui.switch-row
                                    :name="'settings['.$key.']'"
                                    :label="__($schema['label'] ?? $key)"
                                    :hint="isset($schema['description']) ? __($schema['description']) : null"
                                    :checked="$value"
                                    on-value="1"
                                    off-value="0"
                                    :default="$editLocale ? null : $default"
                                    :bleed="false"
                                    :disabled="(bool) $editLocale"
                                />
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            @endforeach

            {{-- Regions: blocks per area of the theme (header, footer …) --}}
            @foreach($regions ?? [] as $regionKey => $regionSchema)
                @php
                    $regionOriginal = $savedRegions[$regionKey] ?? ($regionSchema['default'] ?? []);
                    $regionTranslated = $editLocale
                        ? (json_decode(\App\Support\ThemeTranslations::translated('theme.'.$theme['slug'].'.region.'.$regionKey, $editLocale) ?? '[]', true) ?: [])
                        : null;

                    $regionConfig = [
                        // Translation: its own blocks; "Copy original" fetches those of the original
                        'blocks' => $editLocale ? $regionTranslated : $regionOriginal,
                        'defaultBlocks' => $editLocale ? $regionOriginal : ($regionSchema['default'] ?? []),
                        'previewUrl' => route('backend.pages.preview-blocks'),
                        'saveUrl' => route('backend.themes.save-region', [$theme['slug'], $regionKey]).($editLocale ? '?lang='.$editLocale : ''),
                        'inputName' => 'regions['.$regionKey.']',
                        'csrf' => csrf_token(),
                        'mediaUrl' => route('backend.media.index'),
                        // The same texts as in the page builder
                        't' => \App\Support\BuilderTexts::all((bool) $editLocale),
                        'icons' => \App\Support\ContentIcons::NAMES,
                    ];
                @endphp

                <x-ui.card :title="__($regionSchema['label'] ?? $regionKey)" icon="columns-3" tone="violet">
                    @if(! empty($regionSchema['description']))
                        <p class="ui-help mb-4">{{ __($regionSchema['description']) }}</p>
                    @endif
                    <div data-page-builder='@json($regionConfig)'></div>
                </x-ui.card>
            @endforeach

            {{-- Custom CSS applies to all languages --}}
            @unless($editLocale)
            <x-ui.card :title="__('Custom CSS')" icon="file-code" tone="teal">
                <p class="ui-help mb-3">{{ __('Add custom CSS that will be injected into the frontend.') }}</p>

                {{-- With syntax highlighting (CodeMirror), a normal field without the script --}}
                <div class="flex h-80 overflow-hidden rounded-lg border border-line">
                    <textarea
                        name="custom_css"
                        rows="12"
                        class="ui-textarea h-full w-full rounded-none border-0 font-mono"
                        data-code-editor
                        data-language="css"
                        placeholder="/* CSS */"
                    >{{ $customCss }}</textarea>
                </div>
            </x-ui.card>
            @endunless

            <div class="flex justify-end">
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>

    {{-- One media dialog for all image fields; each button names its target --}}
    <x-ui.media-picker id="theme-media-picker" returns="storage_path" />

</x-app-layout>
