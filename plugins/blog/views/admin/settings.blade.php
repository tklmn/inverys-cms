<x-app-layout>
    <x-slot name="header">{{ __('blog::blog.settings') }}</x-slot>

    @php
        $val = fn (string $key) => old($key, $settings['blog.'.$key] ?? $defaults[$key]);
        $def = fn (string $key) => $defaults[$key];

        $listingSwitches = [
            'show_featured_image' => 'blog::blog.show_featured_image',
            'show_excerpt' => 'blog::blog.show_excerpt',
            'show_date' => 'blog::blog.show_date',
            'show_author' => 'blog::blog.show_author',
            'show_categories' => 'blog::blog.show_categories',
        ];

        $postSwitches = [
            'show_breadcrumbs' => 'blog::blog.show_breadcrumbs',
            'show_prev_next' => 'blog::blog.show_prev_next',
        ];

        $sidebarSwitches = [
            'show_sidebar' => 'blog::blog.show_sidebar',
            'show_rss_link' => 'blog::blog.show_rss_link',
        ];
    @endphp

    <x-ui.page :title="__('blog::blog.settings')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.blog.index')">
                {{ __('blog::blog.posts') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="blog-settings-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <form id="blog-settings-form" action="{{ route('backend.blog.settings.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('blog::blog.url_prefix')" icon="link">
                <div class="ui-grid">
                    <x-ui.setting-field
                        name="url_prefix"
                        :label="__('blog::blog.url_prefix')"
                        :help="__('blog::blog.url_prefix_hint')"
                        :value="$val('url_prefix')"
                        :default="$def('url_prefix')"
                    >
                        <x-ui.input icon="link" id="url_prefix" name="url_prefix" data-field="url_prefix" :value="$val('url_prefix')" required />
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            {{-- Four short fields: dense grid so they sit in one row instead of 3 + 1 --}}
            <x-ui.card :title="__('blog::blog.layout_settings')" icon="layout-grid" tone="violet">
                <div class="ui-grid ui-grid-dense">
                    <x-ui.setting-field
                        name="posts_per_page"
                        :label="__('blog::blog.posts_per_page')"
                        :value="$val('posts_per_page')"
                        :default="$def('posts_per_page')"
                    >
                        <x-ui.input id="posts_per_page" name="posts_per_page" type="number" min="1" max="100" data-field="posts_per_page" :value="$val('posts_per_page')" required />
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="columns"
                        :label="__('blog::blog.columns')"
                        :value="$val('columns')"
                        :default="$def('columns')"
                    >
                        <x-ui.select id="columns" name="columns" data-field="columns">
                            @for($i = 1; $i <= 6; $i++)
                                <option value="{{ $i }}" @selected($val('columns') == $i)>{{ $i }}</option>
                            @endfor
                        </x-ui.select>
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="sort_by"
                        :label="__('blog::blog.sort_by')"
                        :value="$val('sort_by')"
                        :default="$def('sort_by')"
                    >
                        <x-ui.select id="sort_by" name="sort_by" data-field="sort_by">
                            <option value="published_at" @selected($val('sort_by') === 'published_at')>{{ __('blog::blog.published_at') }}</option>
                            <option value="title" @selected($val('sort_by') === 'title')>{{ __('blog::blog.post_title') }}</option>
                            <option value="created_at" @selected($val('sort_by') === 'created_at')>{{ __('translation.created_at') }}</option>
                        </x-ui.select>
                    </x-ui.setting-field>

                    <x-ui.setting-field
                        name="sort_direction"
                        :label="__('blog::blog.sort_direction')"
                        :value="$val('sort_direction')"
                        :default="$def('sort_direction')"
                    >
                        <x-ui.select id="sort_direction" name="sort_direction" data-field="sort_direction">
                            <option value="desc" @selected($val('sort_direction') === 'desc')>{{ __('blog::blog.sort_desc') }}</option>
                            <option value="asc" @selected($val('sort_direction') === 'asc')>{{ __('blog::blog.sort_asc') }}</option>
                        </x-ui.select>
                    </x-ui.setting-field>
                </div>

                {{-- Part of this card, not a separate card with only this switch --}}
                <div class="mt-3">
                    <x-ui.switch-row
                        name="show_pagination"
                        :label="__('blog::blog.show_pagination')"
                        :hint="__('blog::blog.show_pagination_hint')"
                        :checked="$val('show_pagination') == '1'"
                        on-value="1"
                        off-value="0"
                        data-field="show_pagination"
                    />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('blog::blog.listing_elements')" icon="list" tone="teal">
                <div class="flex flex-col">
                    @foreach($listingSwitches as $field => $label)
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

                <div class="ui-grid mt-3">
                    <x-ui.setting-field
                        name="read_more_text"
                        :label="__('blog::blog.read_more_text')"
                        :value="$val('read_more_text')"
                        :default="$def('read_more_text')"
                    >
                        <x-ui.input id="read_more_text" name="read_more_text" data-field="read_more_text" :value="$val('read_more_text')" :placeholder="__('blog::blog.read_more')" />
                    </x-ui.setting-field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('blog::blog.single_post_elements')" icon="file-text">
                <div class="flex flex-col">
                    @foreach($postSwitches as $field => $label)
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

            <x-ui.card :title="__('blog::blog.sidebar_elements')" icon="panel-left" tone="violet">
                <div class="flex flex-col">
                    @foreach($sidebarSwitches as $field => $label)
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
                    {{ __('blog::blog.reset_all_defaults') }}
                </x-ui.button>

                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
