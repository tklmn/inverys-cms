<p class="ui-help mb-4">{{ __('translation.users.delete_account_warning') }}</p>

<x-ui.button variant="danger" size="md" icon="trash-2" data-open-modal="confirm-user-deletion">{{ __('translation.users.delete_account') }}</x-ui.button>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="flex flex-col gap-4 p-5">
        @csrf
        @method('delete')

        <div>
            <h2 class="ui-card-title">{{ __('translation.users.confirm_delete_account') }}</h2>
            <p class="ui-help mt-1">{{ __('translation.users.confirm_delete_account_text') }}</p>
        </div>

        <x-ui.note tone="critical" :title="__('translation.users.warning')">
            <ul class="list-disc ps-5">
                <li>{{ __('translation.users.personal_info_deleted') }}</li>
                <li>{{ __('translation.users.access_revoked') }}</li>
                <li>{{ __('translation.users.cannot_be_undone') }}</li>
            </ul>
        </x-ui.note>

        @include('auth.partials.password', [
            'name' => 'password',
            'id' => 'delete_account_password',
            'label' => __('translation.users.password'),
            'autocomplete' => 'current-password',
            'bag' => 'userDeletion',
        ])

        <div class="flex justify-end gap-2">
            <x-ui.button size="md" data-close-modal>{{ __('translation.cancel') }}</x-ui.button>
            <x-ui.button variant="danger-solid" size="md" icon="trash-2" type="submit">{{ __('translation.users.delete_account') }}</x-ui.button>
        </div>
    </form>
</x-modal>
