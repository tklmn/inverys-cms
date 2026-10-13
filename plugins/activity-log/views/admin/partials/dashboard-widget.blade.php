{{--
    Dashboard tile of the activity log.

    Plugin tiles bring their own frame (the dashboard renders them
    "transparent"), hence x-ui.card here instead of just a header.
--}}
<x-ui.card class="flex h-full flex-col" flush icon="activity" tone="violet">
    <x-slot:head>
        <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.activity-log.index')">
            {{ __('activity-log::activity.view_all') }}
        </x-ui.button>
    </x-slot:head>

    <x-slot:title>{{ __('activity-log::activity.recent_activity') }}</x-slot:title>

    <div class="min-h-0 flex-1 overflow-y-auto">
        <x-ui.list>
            @forelse($recentActivity as $entry)
                @php $entryModel = new \Plugins\ActivityLog\Models\ActivityLog((array) $entry); @endphp

                {{-- Title as in the list: action and subject; who did it is in the metadata --}}
                <x-ui.list-row :title="$entryModel->getDisplayTitle()" :icon="$entryModel->getActionIcon()">
                    <x-slot:meta>
                        <span>{{ $entry->user_name ?? __('activity-log::activity.system') }}</span>
                        <span>{{ format_datetime(\Carbon\Carbon::parse($entry->created_at)) }}</span>
                    </x-slot:meta>
                    <x-slot:cols>
                        <x-ui.list-col>
                            <x-ui.badge :tone="$entryModel->getActionTone()">
                                {{ $entryModel->getActionLabel() }}
                            </x-ui.badge>
                        </x-ui.list-col>
                    </x-slot:cols>
                </x-ui.list-row>
            @empty
                <x-ui.empty icon="activity">{{ __('activity-log::activity.no_activity') }}</x-ui.empty>
            @endforelse
        </x-ui.list>
    </div>
</x-ui.card>
