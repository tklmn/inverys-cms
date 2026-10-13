<x-app-layout>
    <x-slot name="header">{{ __('translation.system.info.title') }}</x-slot>

    @php
        /*
         * The values form groups instead of one long list, and the
         * four figures people actually look for are at the top.
         */
        // "10.11.14-MariaDB-0ubuntu0.24.04.1" breaks any stat tile —
        // only the number goes on top, the full text stays in the group below
        preg_match('/^(\d+(?:\.\d+)+)/', (string) $info['database_version'], $dbMatch);
        $dbShort = $dbMatch[1] ?? $info['database_version'];
        $dbProduct = str_contains(strtolower((string) $info['database_version']), 'mariadb') ? 'MariaDB' : ucfirst((string) $info['database_connection']);

        $groups = [
            [
                'title' => __('translation.system.info.group_application'),
                'icon' => 'package',
                'tone' => 'accent',
                'facts' => [
                    __('translation.system.info.environment') => $info['environment'],
                    __('translation.system.info.app_url') => $info['app_url'],
                    __('translation.system.info.timezone') => $info['timezone'],
                    __('translation.system.info.locale') => $info['locale'],
                    __('translation.system.info.server_time') => format_datetime($info['server_time'], true),
                ],
            ],
            [
                'title' => __('translation.system.info.group_server'),
                'icon' => 'server',
                'tone' => 'violet',
                'facts' => [
                    __('translation.system.info.os') => $info['os'],
                    __('translation.system.info.webserver') => $info['webserver'],
                    __('translation.system.info.db_connection') => $info['database_connection'],
                    __('translation.system.info.db_version') => $info['database_version'],
                ],
            ],
            [
                'title' => __('translation.system.info.group_storage'),
                'icon' => 'hard-drive',
                'tone' => 'teal',
                'facts' => [
                    __('translation.system.info.cache_driver') => $info['cache_driver'],
                    __('translation.system.info.storage_disk') => $info['storage_disk'],
                    __('translation.system.info.log_channel') => $info['log_channel'],
                ],
            ],
            [
                'title' => __('translation.system.info.group_limits'),
                'icon' => 'gauge',
                'tone' => 'caution',
                'facts' => [
                    __('translation.system.info.upload_max_filesize') => $info['upload_max_filesize'],
                    __('translation.system.info.post_max_size') => $info['post_max_size'],
                    // 0 = no limit, otherwise seconds
                    __('translation.system.info.max_execution_time') => (int) $info['max_execution_time'] === 0
                        ? __('translation.system.info.unlimited')
                        : trans_choice('translation.system.info.seconds', (int) $info['max_execution_time'], ['count' => (int) $info['max_execution_time']]),
                    __('translation.system.info.memory_limit') => $info['memory_limit'],
                ],
            ],
        ];
    @endphp

    <x-ui.page :title="__('translation.system.info.title')" :lead="__('translation.system.info.lead')">
        <x-slot:actions>
            <form method="POST" action="{{ route('backend.system.clear-cache') }}">
                @csrf
                <x-ui.button variant="secondary" size="md" icon="refresh-cw" type="submit">
                    {{ __('translation.system.cache.clear') }}
                </x-ui.button>
            </form>
        </x-slot:actions>

        @include('backend.system.partials.tabs')

        <div class="ui-stats">
            <x-ui.stat icon="package" tone="accent" :label="__('translation.system.info.cms_version')" :value="$info['cms_version']" />
            <x-ui.stat icon="layers" tone="caution" :label="__('translation.system.info.laravel_version')" :value="$info['laravel_version']" />
            <x-ui.stat icon="code" tone="violet" :label="__('translation.system.info.php_version')" :value="$info['php_version']" />
            <x-ui.stat icon="database" tone="teal" :label="__('translation.system.info.db_version').' · '.$dbProduct" :value="$dbShort" />
        </div>

        @if($info['debug'])
            <x-ui.note tone="caution" icon="triangle-alert" :title="__('translation.system.info.debug_on_title')">
                {{ __('translation.system.info.debug_on_text') }}
            </x-ui.note>
        @endif

        <div class="ui-grid ui-grid-dense">
            @foreach($groups as $group)
                <x-ui.card :title="$group['title']" :icon="$group['icon']" :tone="$group['tone']">

                    <x-ui.facts class="grid-cols-1!">
                        @foreach($group['facts'] as $label => $value)
                            <x-ui.fact :label="$label">{{ $value !== '' && $value !== null ? $value : '—' }}</x-ui.fact>
                        @endforeach
                    </x-ui.facts>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.card :title="__('translation.system.info.php_extensions').' ('.count($info['php_extensions']).')'" icon="puzzle" tone="neutral">
            <div class="flex flex-wrap gap-1">
                @foreach($info['php_extensions'] as $extension)
                    <x-ui.badge :dot="false">{{ $extension }}</x-ui.badge>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
