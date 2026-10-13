<x-app-layout>
    <x-slot name="header">{{ __('gallery::gallery.create_album') }}</x-slot>

    <x-ui.page :title="__('gallery::gallery.create_album')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.gallery.index')">
                {{ __('gallery::gallery.albums') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="album-create-form">{{ __('translation.create') }}</x-ui.button>
        </x-slot:actions>

        <form id="album-create-form" action="{{ route('backend.gallery.store') }}" method="POST" class="flex flex-col gap-4">
            @csrf

            <x-ui.card>
                <div class="ui-grid">
                    <x-ui.field name="name" :label="__('gallery::gallery.album_name')">
                        <x-ui.input id="name" name="name" :value="old('name')" required autofocus />
                    </x-ui.field>

                    <x-ui.field name="slug" :label="__('gallery::gallery.slug')" optional>
                        <x-ui.input id="slug" name="slug" :value="old('slug')" :placeholder="__('gallery::gallery.auto_generated')" />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('gallery::gallery.description')" class="ui-grid-full" optional>
                        <x-ui.textarea id="description" name="description" rows="3">{{ old('description') }}</x-ui.textarea>
                    </x-ui.field>
                </div>

                <x-ui.switch-row
                    class="mt-3"
                    name="is_active"
                    :label="__('gallery::gallery.active')"
                    :checked="old('is_active', '1') == '1'"
                    on-value="1"
                    off-value="0"
                />
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.gallery.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
