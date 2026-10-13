<x-app-layout>
    <x-slot name="header">{{ __('translation.roles.view') }}</x-slot>

    <x-ui.page :title="$role->label" :lead="$role->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.roles.index')">{{ __('translation.navigation.roles') }}</x-ui.button>
            @permission('edit-role')
                <x-ui.button variant="primary" size="md" icon="pencil" :href="route('backend.roles.edit', $role)">
                    {{ __('translation.edit') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <div class="ui-split">
            <div class="flex min-w-0 flex-col gap-4">
                <x-ui.card :title="__('translation.roles.permissions')" icon="key-round" tone="caution">
                    @if($role->permissions->count())
                        <div class="flex flex-col gap-4">
                            @foreach(\App\Support\PermissionGroup::group($role->permissions) as $group => $permissions)
                                <div>
                                    <p class="ui-section-label mb-1.5 flex items-center gap-1.5">
                                        <x-ui.icon :name="\App\Support\PermissionGroup::icon($group)" />
                                        {{ \App\Support\PermissionGroup::label($group) }}
                                        <span class="ui-tab-count">{{ $permissions->count() }}</span>
                                    </p>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($permissions as $permission)
                                            <x-ui.badge :dot="false" :title="$permission->name">{{ \App\Support\PermissionGroup::permissionLabel($permission->name) }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <x-ui.empty icon="key-round">{{ __('translation.roles.no_permissions') }}</x-ui.empty>
                    @endif
                </x-ui.card>
            </div>

            <aside class="flex flex-col gap-4">
                <x-ui.card :title="__('translation.details')" icon="info" tone="neutral">
                    <x-ui.facts>
                        <x-ui.fact :label="__('translation.roles.name')"><code class="font-mono">{{ $role->name }}</code></x-ui.fact>
                        <x-ui.fact :label="__('translation.roles.type')">
                            <x-ui.badge :tone="$role->is_system ? 'accent' : 'neutral'">
                                {{ $role->is_system ? __('translation.roles.system_tag') : __('translation.roles.custom') }}
                            </x-ui.badge>
                        </x-ui.fact>
                        <x-ui.fact :label="__('translation.roles.permissions')">{{ $role->permissions->count() }} / {{ \App\Models\Permission::count() }}</x-ui.fact>
                        <x-ui.fact :label="__('translation.created_at')">{{ format_date($role->created_at) }}</x-ui.fact>
                    </x-ui.facts>
                </x-ui.card>

                <x-ui.card :title="__('translation.roles.users_with_role')" icon="users" tone="violet" flush>
                    @if($users->isEmpty())
                        <x-ui.empty icon="users">{{ __('translation.roles.no_users') }}</x-ui.empty>
                    @else
                        <x-ui.list>
                            @foreach($users as $user)
                                <x-ui.list-row :title="$user->name" :href="route('backend.users.show', $user)">
                                    <x-slot:lead><x-ui.avatar :user="$user" /></x-slot:lead>
                                    <x-slot:meta><span>{{ $user->email }}</span></x-slot:meta>
                                </x-ui.list-row>
                            @endforeach
                        </x-ui.list>
                    @endif
                </x-ui.card>
            </aside>
        </div>
    </x-ui.page>
</x-app-layout>
