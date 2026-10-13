<x-app-layout>
    <x-slot name="header">{{ __('activity-log::activity.title') }}</x-slot>

    <x-ui.page :title="__('activity-log::activity.title')">
        <x-slot:actions>
            @permission('delete-activity-log')
                <form id="clear-form" action="{{ route('backend.activity-log.clear') }}" method="POST">
                    @csrf
                    <x-ui.button
                        variant="danger"
                        size="md"
                        icon="trash-2"
                        data-delete-modal="{{ json_encode(['formId' => 'clear-form', 'title' => __('activity-log::activity.clear_all'), 'subtitle' => __('activity-log::activity.clear_confirm')]) }}"
                    >{{ __('activity-log::activity.clear_all') }}</x-ui.button>
                </form>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            {{--
                All filters submit on change, the search on Enter —
                a separate filter button would be redundant. The search comes first
                because it stays visible on narrow screens (compact.js).
            --}}
            <x-ui.toolbar :data-filter-label="__('activity-log::activity.filter')">
                <form method="GET" action="{{ route('backend.activity-log.index') }}" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-56"
                        :placeholder="__('activity-log::activity.search')"
                        :aria-label="__('activity-log::activity.search')"
                    />

                    <x-ui.select name="user_id" data-autosubmit class="w-auto" :aria-label="__('activity-log::activity.user')">
                        <option value="">{{ __('activity-log::activity.all_users') }}</option>
                        @foreach($users as $userId => $userName)
                            <option value="{{ $userId }}" @selected(request('user_id') == $userId)>{{ $userName }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="action" data-autosubmit class="w-auto" :aria-label="__('activity-log::activity.action')">
                        <option value="">{{ __('activity-log::activity.all_actions') }}</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ __('activity-log::activity.actions.'.$action) }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="subject_type" data-autosubmit class="w-auto" :aria-label="__('activity-log::activity.subject')">
                        <option value="">{{ __('activity-log::activity.all_types') }}</option>
                        @foreach($subjectTypes as $type)
                            <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ \Plugins\ActivityLog\Models\ActivityLog::subjectTypeLabel($type) }}</option>
                        @endforeach
                    </x-ui.select>

                    {{-- Visible label: otherwise two identical date fields cannot be told apart --}}
                    <label class="flex items-center gap-1.5 text-sm text-fg-muted">
                        <span>{{ __('activity-log::activity.date_from') }}</span>
                        <x-ui.input type="date" name="date_from" value="{{ request('date_from') }}" class="w-auto" data-autosubmit />
                    </label>

                    <label class="flex items-center gap-1.5 text-sm text-fg-muted">
                        <span>{{ __('activity-log::activity.date_to') }}</span>
                        <x-ui.input type="date" name="date_to" value="{{ request('date_to') }}" class="w-auto" data-autosubmit />
                    </label>

                    {{-- Invisible but needed: without a submit button, Enter in the search
                         does not submit when there are several text fields (HTML rule) --}}
                    <button type="submit" class="sr-only" tabindex="-1">{{ __('activity-log::activity.filter') }}</button>
                </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($logs as $log)
                    <x-ui.list-row
                        :title="$log->getDisplayTitle()"
                        :icon="$log->getActionIcon()"
                        :href="route('backend.activity-log.show', $log)"
                    >
                        <x-slot:meta>
                            <span>{{ $log->user_name ?? __('activity-log::activity.system') }}</span>
                            <span>{{ format_datetime($log->created_at, true) }}</span>
                            @if($log->subject_type)
                                <span>{{ $log->getSubjectShortName() }}</span>
                            @endif
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('activity-log::activity.ip_address')" priority="low">
                                {{ $log->ip_address ?? '—' }}
                            </x-ui.list-col>

                            <x-ui.list-col>
                                <x-ui.badge :tone="$log->getActionTone()">{{ $log->getActionLabel() }}</x-ui.badge>
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            <x-ui.button variant="ghost" icon="eye" :label="__('activity-log::activity.details')" :href="route('backend.activity-log.show', $log)" />

                            @permission('delete-activity-log')
                                <form id="delete-log-{{ $log->id }}" action="{{ route('backend.activity-log.destroy', $log) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-log-'.$log->id]) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    {{-- With a filter, not "nothing recorded yet" --}}
                    @php($filtered = collect(['search', 'user_id', 'action', 'subject_type', 'date_from', 'date_to'])->contains(fn ($key) => request()->filled($key)))
                    <x-ui.empty :icon="$filtered ? 'search' : 'activity'">{{ $filtered ? __('translation.no_filter_results') : __('activity-log::activity.no_activity') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($logs->hasPages())
                <x-slot:foot>{{ $logs->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
