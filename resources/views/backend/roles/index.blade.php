<x-app-layout>
    <x-slot name="header">{{ __('translation.roles.management') }}</x-slot>

    @php($totalPermissions = \App\Models\Permission::count())

    <x-ui.page :title="__('translation.roles.management')" :lead="__('translation.roles.lead')">
        <x-slot:actions>
            @permission('create-role')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.roles.create')">
                    {{ __('translation.roles.create') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.list>
                @forelse($roles as $role)
                    @php($granted = $role->permissions->count())

                    <x-ui.list-row :title="$role->label" icon="shield">
                        <x-slot:meta>
                            <span class="font-mono">{{ $role->name }}</span>
                            <span>{{ trans_choice('translation.roles.user_count', $role->users_count ?? 0, ['count' => $role->users_count ?? 0]) }}</span>
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.roles.type')">
                                <x-ui.badge :tone="$role->is_system ? 'accent' : 'neutral'">
                                    {{ $role->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom') }}
                                </x-ui.badge>
                            </x-ui.list-col>
                            <x-ui.list-col :label="__('translation.roles.permissions')">
                                {{ $granted }} / {{ $totalPermissions }}
                            </x-ui.list-col>
                            <x-ui.list-col :label="__('translation.created_at')" priority="low">
                                {{ format_date($role->created_at) }}
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('edit-role')
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.roles.edit', $role)" />
                            @endpermission

                            @permission('delete-role')
                                {{-- System roles cannot be deleted: the button stays visible but disabled --}}
                                @if($role->is_system)
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        :disabled="true"
                                        :disabled-reason="__('translation.roles.system_not_deletable')"
                                    />
                                @else
                                    <form id="delete-form-role-{{ $role->id }}" method="POST" action="{{ route('backend.roles.destroy', $role) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            variant="danger"
                                            icon="trash-2"
                                            :label="__('translation.delete')"
                                            data-delete-modal="{{ json_encode(['formId' => 'delete-form-role-'.$role->id]) }}"
                                        />
                                    </form>
                                @endif
                            @endpermission
                        </x-slot:actions>

                        {{-- Content of the detail panel --}}
                        <x-slot:detail>
                            <x-ui.facts class="mb-4">
                                <x-ui.fact :label="__('translation.roles.type')">
                                    {{ $role->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom') }}
                                </x-ui.fact>
                                <x-ui.fact :label="__('translation.navigation.users')">
                                    {{ $role->users_count ?? 0 }}
                                </x-ui.fact>
                                <x-ui.fact :label="__('translation.roles.permissions')">
                                    {{ $granted }} / {{ $totalPermissions }}
                                </x-ui.fact>
                            </x-ui.facts>

                            @if($granted)
                                <div class="flex flex-col gap-3">
                                    @foreach(\App\Support\PermissionGroup::group($role->permissions) as $group => $permissions)
                                        <div>
                                            <p class="ui-section-label mb-1.5">{{ \App\Support\PermissionGroup::label($group) }}</p>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($permissions as $permission)
                                                    <x-ui.badge :dot="false" :title="$permission->name">{{ \App\Support\PermissionGroup::permissionLabel($permission->name) }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="ui-help">{{ __('translation.roles.no_permissions') }}</p>
                            @endif

                            <div data-panel-actions>
                                @permission('edit-role')
                                    <x-ui.button variant="primary" size="md" icon="pencil" :href="route('backend.roles.edit', $role)">
                                        {{ __('translation.edit') }}
                                    </x-ui.button>
                                @endpermission
                                <x-ui.button variant="secondary" size="md" icon="eye" :href="route('backend.roles.show', $role)">
                                    {{ __('translation.details') }}
                                </x-ui.button>
                            </div>
                        </x-slot:detail>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="shield" :title="__('translation.roles.none_found')">
                        {{ __('translation.roles.none_found_hint') }}
                    </x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
