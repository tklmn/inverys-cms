@props(['permissions', 'selected' => [], 'adminRole' => false])

{{--
    Selection of a role's permissions.
    Grouped via \App\Support\PermissionGroup, with a filter and selection per
    group — with 78 permissions "all or nothing" is too coarse.

    No separate scroll area: the page scrolls, the toolbar
    stays at the top (ui-toolbar-sticky). The group headers are therefore
    not sticky here, otherwise they would slide under the bar.
--}}
@php($grouped = \App\Support\PermissionGroup::group($permissions))

<div class="ui-card" data-permission-picker>
    <x-ui.toolbar class="ui-toolbar-sticky">
        <x-ui.input
            icon="search"
            type="search"
            class="w-full sm:w-64"
            data-filter-input
            :placeholder="__('translation.permissions.filter_placeholder')"
            :aria-label="__('translation.filter')"
        />
        <span class="ui-toolbar-spacer"></span>
        <span class="ui-help tabular-nums"><span data-selected-count>{{ count($selected) }}</span> / {{ count($permissions) }}</span>
        <x-ui.button size="xs" icon="check-check" data-select-all>{{ __('translation.roles.select_all') }}</x-ui.button>
        <x-ui.button size="xs" icon="x" data-deselect-all>{{ __('translation.roles.deselect_all') }}</x-ui.button>
    </x-ui.toolbar>

    <div data-filter-scope>
        @foreach($grouped as $group => $perms)
            @php($groupSelected = $perms->filter(fn ($p) => in_array($p->id, $selected))->count())
            <div data-filter-group>
                <div class="ui-list-group static flex items-center justify-between gap-2">
                    {{-- min-w-0 + truncate: shorten long group names (DE) instead of breaking the row --}}
                    <span class="flex min-w-0 items-center gap-1.5">
                        <x-ui.icon :name="\App\Support\PermissionGroup::icon($group)" class="flex-none" />
                        <span class="truncate">{{ \App\Support\PermissionGroup::label($group) }}</span>
                        <span class="ui-tab-count flex-none whitespace-nowrap tabular-nums"><span data-group-count>{{ $groupSelected }}</span> / {{ $perms->count() }}</span>
                    </span>
                    <x-ui.button variant="ghost" size="xs" icon="check-check" class="flex-none" data-select-group>
                        {{ __('translation.roles.select_group') }}
                    </x-ui.button>
                </div>

                <div class="ui-choices p-2">
                    @foreach($perms as $permission)
                        {{-- Code permissions (App\Support\CodeAccess) only for the admin role --}}
                        @php($codeOnly = ! $adminRole && \App\Support\CodeAccess::isAdminOnly($permission->name))
                        <x-ui.switch-row
                            name="permissions[]"
                            multiple
                            :bleed="false"
                            :label="\App\Support\PermissionGroup::permissionLabel($permission->name)"
                            :hint="$codeOnly ? $permission->name.' · '.__('translation.code_access.role_admin_only') : $permission->name"
                            :on-value="$permission->id"
                            :disabled="$codeOnly"
                            :checked="! $codeOnly && in_array($permission->id, $selected)"
                            :input-id="'permission_'.$permission->id"
                            input-class="permission-checkbox"
                            data-filter-item="{{ Str::lower($permission->name.' '.\App\Support\PermissionGroup::permissionLabel($permission->name)) }}"
                        />
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
