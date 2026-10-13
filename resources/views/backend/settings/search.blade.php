<x-app-layout>
    <x-slot name="header">{{ __('translation.settings.search_title') }}</x-slot>

    <x-ui.page :title="__('translation.settings.search_title')" :lead="__('translation.settings.search_lead')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="search-settings">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="search-settings" method="POST" action="{{ route('backend.settings.search.save') }}" class="flex flex-col gap-4">
            @csrf


            {{-- What the search finds on the left, general options on the right (narrow: below) --}}
            <div class="ui-split">
                <div class="ui-split-main">
                    {{--
                        What the search finds: one row per content type — switch on the right,
                        the searched fields as checkboxes in between.
                    --}}
                    <x-ui.card :title="__('translation.settings.search_content')" icon="layers" tone="violet" flush>
                        <div class="divide-y divide-line">
                            @foreach($searchProviders as $providerKey => $provider)
                                <div class="flex flex-col gap-3 px-4 py-4 xl:flex-row xl:items-center xl:gap-6">
                                    <div class="flex min-w-0 items-center gap-3 xl:w-40 xl:flex-none">
                                        <x-ui.nav-icon :name="['pages' => 'file-text', 'blog' => 'newspaper', 'forms' => 'clipboard-list', 'gallery' => 'images'][$providerKey] ?? 'search'" />
                                        <span class="truncate text-sm font-medium text-fg">{{ $provider['label'] }}</span>
                                    </div>

                                    <fieldset class="flex min-w-0 flex-1 flex-wrap gap-x-1 gap-y-1" data-requires="search_include_{{ $providerKey }}">
                                        <legend class="sr-only">{{ $provider['label'] }} — {{ __('translation.settings.search_fields') }}</legend>
                                        @foreach($provider['fields'] as $fieldKey => $field)
                                            <label class="ui-check-row items-center py-1.5">
                                                <input type="hidden" name="search_{{ $providerKey }}_field_{{ $fieldKey }}" value="off">
                                                <input type="checkbox" name="search_{{ $providerKey }}_field_{{ $fieldKey }}" value="on" @checked($field['enabled'] === 'on') class="!mt-0">
                                                <span class="ui-check-title">{{ $field['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </fieldset>

                                    <div class="xl:flex-none">
                                        <x-ui.toggle
                                            name="search_include_{{ $providerKey }}"
                                            :checked="$provider['enabled'] === 'on'"
                                            :label="__('translation.settings.search_include')"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                </div>

                <div class="ui-split-aside">
                    {{-- General, stacked on the right --}}
                    <x-ui.card :title="__('translation.settings.search_options')" icon="sliders-horizontal" tone="accent">
                        <div class="flex flex-col gap-4">
                            <x-ui.field name="search_min_length" :label="__('translation.settings.search_min_length')">
                                <x-ui.input id="search_min_length" name="search_min_length" type="number" min="1" max="10" :value="$searchSettings['search_min_length']" />
                            </x-ui.field>

                            <x-ui.field name="search_per_page" :label="__('translation.settings.search_per_page')">
                                <x-ui.input id="search_per_page" name="search_per_page" type="number" min="5" max="100" :value="$searchSettings['search_per_page']" />
                            </x-ui.field>

                            <div>
                                <x-ui.switch-row
                                    name="search_guest_access"
                                    :bleed="false"
                                    :label="__('translation.settings.search_guest_access')"
                                    :hint="__('translation.settings.search_guest_hint')"
                                    :checked="$searchSettings['search_guest_access'] === 'on'"
                                />
                            </div>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
