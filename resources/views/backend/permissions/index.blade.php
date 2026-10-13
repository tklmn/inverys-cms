<x-app-layout>
    <x-slot name="header">{{ __('translation.permissions.management') }}</x-slot>

    @php($grouped = \App\Support\PermissionGroup::group($permissions))

    <x-ui.page :title="__('translation.permissions.management')" :lead="__('translation.permissions.lead')">
        <x-slot:actions>
            @permission('create-permission')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.permissions.create')">
                    {{ __('translation.permissions.create') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <x-ui.input
                    icon="search"
                    type="search"
                    class="w-full sm:w-72"
                    data-filter-input
                    :placeholder="__('translation.permissions.filter_placeholder')"
                    aria-label="{{ __('translation.filter') }}"
                />
                <span class="ui-toolbar-spacer"></span>
                <span class="ui-help" data-filter-count>
                    {{ trans_choice('translation.permissions.count', $permissions->count(), ['count' => $permissions->count()]) }}
                </span>
            </x-ui.toolbar>

            <x-ui.list data-filter-scope>
                @forelse($grouped as $group => $groupPermissions)
                    <x-ui.list-group data-filter-group>
                        <span class="flex items-center gap-1.5">
                            <x-ui.icon :name="\App\Support\PermissionGroup::icon($group)" />
                            {{ \App\Support\PermissionGroup::label($group) }}
                            <span class="ui-tab-count">{{ $groupPermissions->count() }}</span>
                        </span>
                    </x-ui.list-group>

                    @foreach($groupPermissions as $permission)
                        @php($roles = $permission->roles ?? collect())

                        <x-ui.list-row
                            :title="\App\Support\PermissionGroup::permissionLabel($permission->name)"
                            :icon="\App\Support\PermissionGroup::icon($group)"
                            data-filter-item="{{ Str::lower($permission->name.' '.\App\Support\PermissionGroup::permissionLabel($permission->name).' '.\App\Support\PermissionGroup::label($group)) }}"
                        >
                            <x-slot:meta>
                                <span class="font-mono">{{ $permission->name }}</span>
                                @if($permission->is_system)
                                    <span>{{ __('translation.roles.system_tag') }}</span>
                                @endif
                            </x-slot:meta>

                            <x-slot:cols>
                                <x-ui.list-col :label="__('translation.permissions.used_by')">
                                    <span class="flex flex-wrap gap-1">
                                        @forelse($roles as $role)
                                            <x-ui.badge :tone="$role->name === 'admin' ? 'accent' : 'neutral'">{{ $role->label }}</x-ui.badge>
                                        @empty
                                            <span class="text-fg-muted">{{ __('translation.permissions.unused') }}</span>
                                        @endforelse
                                    </span>
                                </x-ui.list-col>
                            </x-slot:cols>

                            <x-slot:actions>
                                {{-- Permissions checked by the code: visible but locked --}}
                                @permission('edit-permission')
                                    <x-ui.button
                                        variant="ghost"
                                        icon="pencil"
                                        :label="__('translation.edit')"
                                        :href="route('backend.permissions.edit', $permission)"
                                        :disabled="$permission->is_system"
                                        :disabled-reason="__('translation.permissions.system_locked')"
                                    />
                                @endpermission

                                @permission('delete-permission')
                                    @if($permission->is_system)
                                        <x-ui.button
                                            variant="danger"
                                            icon="trash-2"
                                            :label="__('translation.delete')"
                                            :disabled="true"
                                            :disabled-reason="__('translation.permissions.system_locked')"
                                        />
                                    @else
                                        <form id="delete-form-permission-{{ $permission->id }}" method="POST" action="{{ route('backend.permissions.destroy', $permission) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button
                                                variant="danger"
                                                icon="trash-2"
                                                :label="__('translation.delete')"
                                                data-delete-modal="{{ json_encode(['formId' => 'delete-form-permission-'.$permission->id, 'title' => \App\Support\PermissionGroup::permissionLabel($permission->name), 'subtitle' => $roles->isEmpty() ? null : trans_choice('translation.permissions.used_by_roles', $roles->count(), ['count' => $roles->count()])]) }}"
                                            />
                                        </form>
                                    @endif
                                @endpermission
                            </x-slot:actions>

                            <x-slot:detail>
                                <x-ui.facts class="mb-4">
                                    <x-ui.fact :label="__('translation.permissions.name')">
                                        <code class="font-mono text-xs">{{ $permission->name }}</code>
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.permissions.group')">
                                        {{ \App\Support\PermissionGroup::label($group) }}
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.roles.type')">
                                        {{ $permission->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom') }}
                                    </x-ui.fact>
                                </x-ui.facts>

                                <p class="ui-section-label mb-1.5">{{ __('translation.permissions.used_by') }}</p>
                                @if($roles->isEmpty())
                                    <p class="ui-help">{{ __('translation.permissions.unused_hint') }}</p>
                                @else
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($roles as $role)
                                            <x-ui.badge tone="accent" :dot="false">{{ $role->label }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                @endif
                            </x-slot:detail>
                        </x-ui.list-row>
                    @endforeach
                @empty
                    <x-ui.empty icon="key-round" :title="__('translation.permissions.none_found')">
                        {{ __('translation.permissions.none_found_hint') }}
                    </x-ui.empty>
                @endforelse

                <div class="ui-empty hidden" data-filter-empty>
                    <span class="ui-empty-icon"><x-ui.icon name="search" /></span>
                    <p class="ui-empty-text">{{ __('translation.no_results') }}</p>
                </div>
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
