<x-app-layout>
    <x-slot name="header">{{ __('translation.users.management') }}</x-slot>

    <x-ui.page :title="__('translation.users.management')">
        <x-slot:actions>
            @permission('create-user')
                <x-ui.button variant="primary" size="md" icon="user-plus" :href="route('backend.users.create')">
                    {{ __('translation.users.create') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            {{-- Instant filter by name, email or role (ui/list-filter.js) --}}
            <x-ui.toolbar>
                <x-ui.input
                    icon="search"
                    type="search"
                    class="w-full sm:w-72"
                    data-filter-input
                    :placeholder="__('translation.users.filter_placeholder')"
                    aria-label="{{ __('translation.filter') }}"
                />
            </x-ui.toolbar>

            <x-ui.list data-filter-scope>
                @forelse ($users as $user)
                    <x-ui.list-row :title="$user->name" :href="route('backend.users.show', $user)" data-filter-item="{{ Str::lower($user->name.' '.$user->email.' '.$user->roles->map->label->join(' ')) }}">
                        <x-slot:lead><x-ui.avatar :user="$user" /></x-slot:lead>

                        <x-slot:meta>
                            <span>{{ $user->email }}</span>
                            @if($user->job_title)<span>{{ $user->job_title }}</span>@endif
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.users.roles')">
                                <span class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <x-ui.badge :tone="$role->name === 'admin' ? 'accent' : 'neutral'">{{ $role->label }}</x-ui.badge>
                                    @empty
                                        —
                                    @endforelse
                                </span>
                            </x-ui.list-col>

                            <x-ui.list-col :label="__('translation.users.last_login')" priority="low">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : '—' }}
                            </x-ui.list-col>

                            <x-ui.list-col>
                                @if($user->is_active)
                                    <x-ui.badge tone="positive">{{ __('translation.users.active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge tone="critical">{{ __('translation.users.inactive') }}</x-ui.badge>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @php
                                // Mirrors the checks in UserController (denyAdminAccountChange, destroy)
                                $isAdmin = $user->roles->contains('name', 'admin');
                                $isSelf = $user->id === auth()->id();
                                $moreRights = ! $isSelf && ! $isAdmin && ! auth()->user()->canGrant($user->permissionNames());
                                $editBlocked = match (true) {
                                    $isAdmin && ! auth()->user()->hasRole('admin') => __('translation.users.error.admin_edit'),
                                    $moreRights => __('translation.users.error.more_rights'),
                                    default => null,
                                };
                            @endphp

                            @permission('edit-user')
                                @if($editBlocked)
                                    <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :disabled="true" :disabled-reason="$editBlocked" />
                                @else
                                    <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.users.edit', $user)" />
                                @endif
                            @endpermission

                            @permission('delete-user')
                                @php
                                    $blockedReason = match (true) {
                                        $isSelf => __('translation.users.error.self_delete'),
                                        $isAdmin && ! auth()->user()->hasRole('admin') => __('translation.users.error.admin_delete'),
                                        $isAdmin && $adminCount <= 1 => __('translation.users.error.last_admin'),
                                        $moreRights => __('translation.users.error.more_rights'),
                                        default => null,
                                    };
                                @endphp

                                @if($blockedReason)
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        :disabled="true"
                                        :disabled-reason="$blockedReason"
                                    />
                                @else
                                    <form id="delete-form-user-{{ $user->id }}" action="{{ route('backend.users.destroy', $user) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            variant="danger"
                                            icon="trash-2"
                                            :label="__('translation.delete')"
                                            data-delete-modal="{{ json_encode(['formId' => 'delete-form-user-'.$user->id]) }}"
                                        />
                                    </form>
                                @endif
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="users">{{ __('translation.users.no_users_found') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($users->hasPages())
                <x-slot:foot>{{ $users->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
