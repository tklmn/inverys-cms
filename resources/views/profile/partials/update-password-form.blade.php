{{-- Change password: the same fields and rule hints as at login --}}
<form method="post" action="{{ route('password.update') }}" class="flex flex-col gap-4">
    @csrf
    @method('put')

    <p class="ui-help">{{ __('translation.users.password_security') }}</p>

    @include('auth.partials.password', [
        'name' => 'current_password',
        'id' => 'update_password_current_password',
        'label' => __('translation.users.current_password'),
        'autocomplete' => 'current-password',
        'bag' => 'updatePassword',
    ])

    <div class="ui-grid">
        @include('auth.partials.password', [
            'name' => 'password',
            'id' => 'update_password_password',
            'label' => __('translation.users.new_password'),
            'autocomplete' => 'new-password',
            'bag' => 'updatePassword',
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'id' => 'update_password_password_confirmation',
            'label' => __('translation.users.confirm_password'),
            'autocomplete' => 'new-password',
            'bag' => 'updatePassword',
        ])
    </div>

    @include('auth.partials.password-hints', ['for' => 'update_password_password'])

    <div class="flex justify-end">
        <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.users.password_update') }}</x-ui.button>
    </div>
</form>
