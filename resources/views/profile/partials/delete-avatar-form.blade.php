<x-modal name="confirm-avatar-deletion" focusable>
    <form method="post" action="{{ route('profile.avatar.delete') }}" class="flex flex-col gap-4 p-5">
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
            <x-ui.button variant="danger-solid" size="md" icon="trash-2" type="submit">{{ __('translation.users.delete_avatar') }}</x-ui.button>
        </div>
    </form>
</x-modal>
