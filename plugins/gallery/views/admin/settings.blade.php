<x-app-layout>
    <x-slot name="header">{{ __('gallery::gallery.settings') }}</x-slot>

    @php
        // Value and default per setting — the reset button only appears
        // while the value differs from the default.
        $val = fn (string $key) => old($key, $settings['gallery.'.$key] ?? $defaults[$key]);
        $def = fn (string $key) => $defaults[$key];

        $switches = [
            'lightbox' => 'gallery::gallery.lightbox',
            'show_captions' => 'gallery::gallery.show_captions',
            'lazy_loading' => 'gallery::gallery.lazy_loading',
        ];
    @endphp

    <x-ui.page :title="__('gallery::gallery.settings')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.gallery.index')">
                {{ __('gallery::gallery.albums') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="gallery-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="gallery-settings-form" action="{{ route('backend.gallery.settings.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('gallery::gallery.grid_layout')" icon="layout-grid">
                <div class="ui-grid ui-grid-dense">
                    <x-ui.setting-field
                        name="columns"
                        :label="__('gallery::gallery.columns')"
                        :help="__('gallery::gallery.columns_hint')"
                        :value="$val('columns')"
                        :default="$def('columns')"
                    >
                        <x-ui.select id="columns" name="columns" data-field="columns">
                            @foreach([2, 3, 4, 5, 6] as $option)
                                <option value="{{ $option }}" @selected((string) $val('columns') === (string) $option)>{{ $option }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="gap"
                        :label="__('gallery::gallery.gap')"
                        :help="__('gallery::gallery.gap_hint')"
                        :value="$val('gap')"
                        :default="$def('gap')"
                    >
                        <x-ui.select id="gap" name="gap" data-field="gap">
                            @foreach([2 => __('gallery::gallery.gap_2'), 4 => __('gallery::gallery.gap_4'), 6 => __('gallery::gallery.gap_6'), 8 => __('gallery::gallery.gap_8')] as $option => $optionLabel)
                                <option value="{{ $option }}" @selected((string) $val('gap') === (string) $option)>{{ $optionLabel }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="aspect_ratio"
                        :label="__('gallery::gallery.aspect_ratio')"
                        :help="__('gallery::gallery.aspect_ratio_hint')"
                        :value="$val('aspect_ratio')"
                        :default="$def('aspect_ratio')"
                    >
                        <x-ui.select id="aspect_ratio" name="aspect_ratio" data-field="aspect_ratio">
                            @foreach(['cover' => __('gallery::gallery.ratio_cover'), 'contain' => __('gallery::gallery.ratio_contain'), 'auto' => __('gallery::gallery.ratio_auto')] as $option => $optionLabel)
                                <option value="{{ $option }}" @selected((string) $val('aspect_ratio') === (string) $option)>{{ $optionLabel }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="thumbnail_size"
                        :label="__('gallery::gallery.thumbnail_size')"
                        :help="__('gallery::gallery.thumbnail_size_hint')"
                        :value="$val('thumbnail_size')"
                        :default="$def('thumbnail_size')"
                    >
                        <x-ui.select id="thumbnail_size" name="thumbnail_size" data-field="thumbnail_size">
                            @foreach(['small' => __('gallery::gallery.size_small'), 'medium' => __('gallery::gallery.size_medium'), 'large' => __('gallery::gallery.size_large')] as $option => $optionLabel)
                                <option value="{{ $option }}" @selected((string) $val('thumbnail_size') === (string) $option)>{{ $optionLabel }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('gallery::gallery.pagination')" icon="layers" tone="violet">
                <x-ui.switch-row
                    name="pagination"
                    :label="__('gallery::gallery.pagination')"
                    :hint="__('gallery::gallery.pagination_hint')"
                    :checked="$val('pagination') == '1'"
                    on-value="1"
                    off-value="0"
                    data-field="pagination"
                />

                {{-- Only takes effect with pagination (see the gallery block) --}}
                <div class="ui-grid mt-3" data-requires="pagination">
                    <x-ui.setting-field
                        name="images_per_page"
                        :label="__('gallery::gallery.images_per_page')"
                        :help="__('gallery::gallery.images_per_page_hint')"
                        :value="$val('images_per_page')"
                        :default="$def('images_per_page')"
                    >
                        <x-ui.input
                            id="images_per_page"
                            name="images_per_page"
                            type="number"
                            min="0"
                            max="200"
                            data-field="images_per_page"
                            :value="$val('images_per_page')"
                        />
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('gallery::gallery.display_options')" icon="eye" tone="teal">
                <div class="flex flex-col">
                    @foreach($switches as $field => $label)
                        <x-ui.switch-row
                            :name="$field"
                            :label="__($label)"
                            :checked="$val($field) == '1'"
                            on-value="1"
                            off-value="0"
                            :data-field="$field"
                        />
                    @endforeach
                </div>
            </x-ui.card>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <x-ui.button variant="ghost" size="md" icon="rotate-ccw" id="reset-all-btn">
                    {{ __('gallery::gallery.reset_all_defaults') }}
                </x-ui.button>

                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>

</x-app-layout>
