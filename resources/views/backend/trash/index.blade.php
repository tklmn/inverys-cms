<x-app-layout>
    <x-slot name="header">{{ __('translation.trash') }}</x-slot>

    @php
        // The controller passes the active tab in $tab — keep it
        // before the loop below reuses the same name.
        $activeTab = $tab;

        /*
         * All tabs in one structure instead of seven nearly identical tables.
         * Each tab provides title, subtitle and type — the rest is the same.
         */
        $tabs = [
            [
                'key' => 'pages',
                'type' => 'page',
                'label' => __('translation.navigation.pages'),
                'icon' => 'file-text',
                'items' => $pages,
                'title' => fn ($item) => $item->title,
                'meta' => fn ($item) => array_filter([
                    '/'.$item->slug,
                    $item->parent ? __('translation.pages.parent').': '.$item->parent->title : null,
                ]),
            ],
            [
                'key' => 'users',
                'type' => 'user',
                'label' => __('translation.dashboard.users'),
                'icon' => 'users',
                'items' => $users,
                'title' => fn ($item) => $item->name,
                'meta' => fn ($item) => [$item->email],
            ],
            [
                'key' => 'media',
                'type' => 'media',
                'label' => __('translation.navigation.media'),
                'icon' => 'image',
                'items' => $media,
                'title' => fn ($item) => $item->name ?? $item->file_name,
                'meta' => fn ($item) => array_filter([$item->file_name ?? null]),
            ],
            [
                'key' => 'roles',
                'type' => 'role',
                'label' => __('translation.navigation.roles'),
                'icon' => 'shield',
                'items' => $roles,
                'title' => fn ($item) => $item->name,
                'meta' => fn ($item) => [],
            ],
            [
                'key' => 'permissions',
                'type' => 'permission',
                'label' => __('translation.navigation.permissions'),
                'icon' => 'key-round',
                'items' => $permissions,
                'title' => fn ($item) => $item->name,
                'meta' => fn ($item) => [],
            ],
            [
                'key' => 'plugins',
                'type' => 'plugin',
                'label' => __('translation.navigation.plugins'),
                'icon' => 'puzzle',
                'items' => $plugins,
                // Plugin names are in English in the manifest
                'title' => fn ($item) => __($item->name),
                'meta' => fn ($item) => [],
            ],
            [
                'key' => 'themes',
                'type' => 'theme',
                'label' => __('translation.navigation.themes'),
                'icon' => 'palette',
                'items' => $trashedThemes,
                'title' => fn ($item) => $item->name,
                'meta' => fn ($item) => [],
            ],
        ];

        // Tabs registered by plugins
        foreach ($pluginTrash as $pTab) {
            $column = $pTab['title_column'];

            $tabs[] = [
                'key' => $pTab['type'].'s',
                'type' => $pTab['type'],
                'label' => __($pTab['label']),
                'icon' => $pTab['icon'],
                'items' => $pTab['items'],
                'title' => fn ($item) => $item->{$column},
                'meta' => fn ($item) => [],
            ];
        }
    @endphp

    @php
        // Only tabs the controller allows (permission per type)
        $tabs = array_values(array_filter($tabs, fn ($t) => array_key_exists($t['key'], $counts)));
        $current = collect($tabs)->firstWhere('key', $activeTab) ?? $tabs[0];
        $total = array_sum($counts);
    @endphp

    <x-ui.page :title="__('translation.trash')" :lead="__('translation.trash_lead')">
        <x-slot:actions>
            <x-ui.badge :tone="$total ? 'caution' : 'neutral'">{{ trans_choice('translation.trash_total', $total, ['count' => $total]) }}</x-ui.badge>
        </x-slot:actions>

        <x-ui.card flush>
            {{--
                Tabs as links: the controller only loads the active tab.
                Empty tabs stay visible, but muted.
            --}}
            <nav class="ui-tabs" aria-label="{{ __('translation.trash') }}">
                @foreach($tabs as $tab)
                    @php($count = $counts[$tab['key']] ?? 0)
                    <a
                        href="{{ route('backend.trash.index', ['tab' => $tab['key']]) }}"
                        class="ui-tab {{ $count ? '' : 'ui-tab-muted' }}"
                        @if($tab['key'] === $current['key']) aria-selected="true" aria-current="page" @endif
                    >
                        <x-ui.nav-icon :name="$tab['icon']" />
                        {{ $tab['label'] }}
                        <span class="ui-tab-count">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>

            <x-ui.list>
                @forelse($current['items'] as $item)
                    <x-ui.list-row :title="($current['title'])($item)" :icon="$current['icon']">
                        <x-slot:meta>
                            @foreach(($current['meta'])($item) as $line)
                                <span>{{ $line }}</span>
                            @endforeach
                            <span>{{ __('translation.deleted_at') }}: {{ format_datetime($item->deleted_at) }}</span>
                        </x-slot:meta>

                        <x-slot:actions>
                            @include('backend.trash.partials.actions', ['type' => $current['type'], 'item' => $item])
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    @include('backend.trash.partials.empty')
                @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
