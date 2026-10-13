<x-app-layout>
    <x-slot name="header">{{ $log->getActionLabel() }}</x-slot>

    {{-- Page title = what happened; the card below is called "Details" --}}
    <x-ui.page :title="$log->getActionLabel()" :lead="$log->subject_label">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.activity-log.index')">
                {{ __('activity-log::activity.title') }}
            </x-ui.button>

            @permission('delete-activity-log')
                <form id="delete-form-log-{{ $log->id }}" action="{{ route('backend.activity-log.destroy', $log) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" size="md" icon="trash-2" data-delete-modal="{{ json_encode(['formId' => 'delete-form-log-'.$log->id]) }}">
                        {{ __('translation.delete') }}
                    </x-ui.button>
                </form>
            @endpermission
        </x-slot:actions>

        <x-ui.card :title="__('activity-log::activity.details')" icon="info">
            <x-ui.facts>
                <x-ui.fact :label="__('activity-log::activity.date')">{{ format_datetime($log->created_at, true) }}</x-ui.fact>

                <x-ui.fact :label="__('activity-log::activity.user')">{{ $log->user_name ?? __('activity-log::activity.system') }}</x-ui.fact>

                <x-ui.fact :label="__('activity-log::activity.action')">
                    {{-- Only the badge dot carries the colour, no icon next to it --}}
                    <x-ui.badge :tone="$log->getActionTone()">{{ $log->getActionLabel() }}</x-ui.badge>
                </x-ui.fact>

                @if($log->subject_type)
                    <x-ui.fact :label="__('activity-log::activity.subject')">
                        {{ $log->subject_label ?? '—' }}
                        <span class="text-fg-muted">({{ $log->getSubjectShortName() }} #{{ $log->subject_id }})</span>
                    </x-ui.fact>
                @endif

                <x-ui.fact :label="__('activity-log::activity.ip_address')">{{ $log->ip_address ?? '—' }}</x-ui.fact>

                {{-- Long string: own full row, breaks at word boundaries --}}
                <x-ui.fact :label="__('activity-log::activity.user_agent')" class="ui-grid-full">
                    <span class="break-words font-normal">{{ $log->user_agent ?? '—' }}</span>
                </x-ui.fact>
            </x-ui.facts>
        </x-ui.card>

        @if($log->action === 'updated' && $log->properties && (isset($log->properties['old']) || isset($log->properties['new'])))
            @include('activity-log-admin::partials.diff-table', ['properties' => $log->properties])
        @elseif($log->action === 'created' && $log->properties && isset($log->properties['attributes']))
            <x-ui.card :title="__('activity-log::activity.properties')" icon="list" tone="violet" flush>
                <x-ui.list>
                    @foreach($log->properties['attributes'] as $field => $value)
                        <x-ui.list-row :title="$field" icon="plus">
                            <x-slot:cols>
                                <x-ui.list-col :label="__('activity-log::activity.new_value')">
                                    {{ is_array($value) ? json_encode($value) : $value }}
                                </x-ui.list-col>
                            </x-slot:cols>
                        </x-ui.list-row>
                    @endforeach
                </x-ui.list>
            </x-ui.card>
        @elseif($log->properties && $log->action === 'failed_login')
            <x-ui.card :title="__('activity-log::activity.properties')" icon="list" tone="violet">
                <x-ui.facts>
                    <x-ui.fact :label="__('translation.email')">{{ $log->properties['email'] ?? '—' }}</x-ui.fact>
                </x-ui.facts>
            </x-ui.card>
        @endif
    </x-ui.page>
</x-app-layout>
