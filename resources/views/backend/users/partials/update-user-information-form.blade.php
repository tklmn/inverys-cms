{{--
    Shared form for creating and editing a user.
    $user is not set when creating.
--}}
@php
    $editing = isset($user);
    // Your own account: deactivating yourself or revoking your own admin role
    // locks you out — the controller enforces both as well.
    $isSelf = $editing && $user->id === auth()->id();
@endphp

<form
    id="user-form"
    action="{{ $editing ? route('backend.users.update', $user) : route('backend.users.store') }}"
    method="POST"
    enctype="multipart/form-data"
    class="flex flex-col gap-4"
>
    @csrf
    @if($editing)
        @method('PUT')
    @endif

    {{--
        Two columns: on the left who the person is (profile), on the right what they
        may do (picture, access, roles), so the roles are reachable without
        scrolling.
    --}}
    <div class="ui-split">
        <div class="ui-split-main">
            <x-ui.card :title="__('translation.users.personal_information')" icon="user-round">
                <div class="flex flex-col gap-4">
                    <div class="ui-grid">
                        <x-ui.field name="first_name" :label="__('translation.users.first_name')">
                            <x-ui.input id="first_name" name="first_name" :value="old('first_name', $editing ? $user->first_name : '')" required autofocus />
                        </x-ui.field>

                        <x-ui.field name="last_name" :label="__('translation.users.last_name')">
                            <x-ui.input id="last_name" name="last_name" :value="old('last_name', $editing ? $user->last_name : '')" required />
                        </x-ui.field>
                    </div>

                    <div class="ui-grid">
                        <x-ui.field name="name" :label="__('translation.name')">
                            <x-ui.input id="name" name="name" icon="at-sign" :value="old('name', $editing ? $user->name : '')" required />
                        </x-ui.field>

                        <x-ui.field name="email" :label="__('translation.email')">
                            <x-ui.input id="email" name="email" type="email" icon="mail" :value="old('email', $editing ? $user->email : '')" required />
                        </x-ui.field>
                    </div>

                    <div class="ui-grid">
                        <x-ui.field name="phone" :label="__('translation.users.phone')" optional>
                            <x-ui.input id="phone" name="phone" type="tel" :value="old('phone', $editing ? $user->phone : '')" />
                        </x-ui.field>

                        <x-ui.field name="job_title" :label="__('translation.users.job_title')" optional>
                            <x-ui.input id="job_title" name="job_title" :value="old('job_title', $editing ? $user->job_title : '')" />
                        </x-ui.field>
                    </div>

                    <x-ui.field name="bio" :label="__('translation.users.bio')" optional>
                        <x-ui.textarea id="bio" name="bio" rows="6">{{ old('bio', $editing ? $user->bio : '') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>
        </div>

        <div class="ui-split-aside">
            <x-ui.card :title="__('translation.users.avatar')" icon="image" tone="violet">
                {{-- When creating there is nothing to show or delete yet --}}
                @if($editing)
                    <div class="mb-4 flex items-center gap-4">
                        <x-ui.avatar :user="$user" size="lg" />

                        {{-- Delete always visible, greyed out while there is no picture --}}
                        <x-ui.button
                            variant="danger"
                            icon="trash-2"
                            :disabled="! $user->avatar"
                            :disabled-reason="__('translation.users.no_avatar')"
                            :data-open-modal="'confirm-avatar-deletion-'.$user->id"
                        >
                            {{ __('translation.users.delete_avatar') }}
                        </x-ui.button>
                    </div>
                @endif

                <div>
                    <x-ui.dropzone
                        name="avatar"
                        accept="image/*"
                        compact
                        :label="__('translation.users.upload_new_avatar')"
                        :hint="__('translation.users.file_formats')"
                    />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('translation.users.access')" icon="key-round" tone="teal">
                <div class="flex flex-col gap-4">
                    <div>
                        <x-ui.switch-row
                            name="is_active"
                            :label="__('translation.users.active')"
                            :hint="$isSelf ? __('translation.users.self_active_locked') : __('translation.users.active_hint')"
                            :checked="$isSelf || (bool) old('is_active', $editing ? $user->is_active : true)"
                            :disabled="$isSelf"
                            on-value="1"
                            off-value="0"
                        />
                        <x-input-error :messages="$errors->get('is_active')" />
                    </div>

                    @if($editing)
                        <p class="ui-help">{{ __('translation.users.password_empty') }}</p>
                    @endif

                    <x-ui.field name="password" :label="__('translation.users.password')">
                        <x-ui.input id="password" name="password" type="password" icon="lock" autocomplete="new-password" :required="! $editing" />
                    </x-ui.field>

                    <x-ui.field name="password_confirmation" :label="__('translation.users.confirm_password')">
                        <x-ui.input id="password_confirmation" name="password_confirmation" type="password" icon="lock" :required="! $editing" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('translation.users.role_assignment')" icon="shield" tone="caution">
                <div class="flex flex-col">
                    @foreach($roles as $role)
                        @php($ownAdmin = $isSelf && $role->name === 'admin' && $user->roles->contains($role->id))
                        <x-ui.switch-row
                            name="roles[]"
                            multiple
                            :label="$role->label"
                            :hint="$ownAdmin ? __('translation.users.self_admin_locked') : ($role->is_system ? __('translation.roles.system_tag') : null)"
                            :on-value="$role->id"
                            :checked="$ownAdmin || ($editing && $user->roles->contains($role->id)) || (is_array(old('roles')) && in_array($role->id, old('roles')))"
                            :disabled="$ownAdmin"
                            :input-id="'role_'.$role->id"
                        />
                        {{-- Disabled switches are not submitted — the role is kept anyway --}}
                        @if($ownAdmin)
                            <input type="hidden" name="roles[]" value="{{ $role->id }}">
                        @endif
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-2" />
            </x-ui.card>
        </div>
    </div>

    <div class="flex justify-end gap-2">
        <x-ui.button size="md" :href="route('backend.users.index')">{{ __('translation.cancel') }}</x-ui.button>
        <x-ui.button variant="primary" size="md" icon="check" type="submit">
            {{ $editing ? __('translation.update') : __('translation.create') }}
        </x-ui.button>
    </div>
</form>

@if($editing)
    <x-modal name="confirm-avatar-deletion-{{ $user->id }}" focusable>
        <form method="post" action="{{ route('backend.users.avatar.delete', $user->id) }}" class="flex flex-col gap-4 p-5">
            @csrf
            @method('delete')

            <div>
                <h2 class="ui-card-title">{{ __('translation.users.confirm_delete_avatar') }}</h2>
                <p class="ui-help mt-1">{{ __('translation.users.confirm_delete_avatar_text') }}</p>
            </div>

            <x-ui.note tone="caution" :title="__('translation.users.note')">
                {{ __('translation.users.avatar_delete_note') }}
            </x-ui.note>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" data-close-modal>{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="danger-solid" size="md" icon="trash-2" type="submit">
                    {{ __('translation.users.delete_avatar') }}
                </x-ui.button>
            </div>
        </form>
    </x-modal>
@endif
