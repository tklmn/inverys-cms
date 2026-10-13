<x-app-layout>
    <x-slot name="header">{{ __('translation.logs.title') }}</x-slot>

    <x-ui.page :title="__('translation.logs.title')" :lead="__('translation.logs.lead')">
        <x-ui.card flush>
            <x-ui.list>
                @forelse ($logs as $log)
                    <x-ui.list-row :title="$log['name']" icon="scroll-text" :href="route('backend.logs.show', $log['name'])">
                        <x-slot:meta>
                            <span>{{ __('translation.logs.modified') }}: {{ format_datetime($log['modified']) }}</span>
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.logs.size')">{{ $log['size'] }}</x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('download-logs')
                                <x-ui.button variant="ghost" icon="download" :label="__('translation.logs.download')" :href="route('backend.logs.download', $log['name'])" />
                            @endpermission

                            @permission('clear-logs')
                                <form id="delete-form-log-{{ $loop->index }}" action="{{ route('backend.logs.clear', $log['name']) }}" method="POST">
                                    @csrf
                                    <x-ui.button
                                        variant="danger"
                                        icon="eraser"
                                        :label="__('translation.logs.clear')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-form-log-'.$loop->index, 'title' => __('translation.logs.confirm_clear'), 'subtitle' => $log['name']]) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="scroll-text">{{ __('translation.logs.none_found') }}</x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
