<x-app-layout>
    <x-slot name="header">{{ __('translation.media.upload_file') }}</x-slot>

    <x-ui.page :title="__('translation.media.upload_file')">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.media.index')">{{ __('translation.media.title') }}</x-ui.button>
        </x-slot:actions>

        <form action="{{ route('backend.media.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
            @csrf

            <x-ui.card>
                @php($types = \App\Http\Controllers\Admin\MediaController::UPLOAD_TYPES)
                <x-ui.field name="file">
                    <x-ui.dropzone
                        name="file"
                        icon="upload"
                        :accept="'.'.implode(',.', $types)"
                        :label="__('translation.media.drop_files')"
                        :hint="__('translation.media.allowed_types').': '.strtoupper(implode(', ', array_diff($types, ['jpeg']))).' · '.__('translation.media.max_size').' '.round(config('cms.max_upload_size') / 1024).' MB'"
                        required
                    />
                </x-ui.field>
            </x-ui.card>

            <x-ui.card :title="__('translation.media.media_details')" icon="info" tone="accent">
                <div class="flex flex-col gap-4">
                    <x-ui.field name="alt_text" :label="__('translation.media.alt_text')" :help="__('translation.media.alt_text_help')" optional>
                        <x-ui.input id="alt_text" name="alt_text" :value="old('alt_text')" />
                    </x-ui.field>

                    <x-ui.field name="description" :label="__('translation.media.description')" optional>
                        <x-ui.textarea id="description" name="description" rows="3">{{ old('description') }}</x-ui.textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button size="md" :href="route('backend.media.index')">{{ __('translation.cancel') }}</x-ui.button>
                <x-ui.button variant="primary" size="md" icon="upload" type="submit">{{ __('translation.media.upload') }}</x-ui.button>
            </div>
        </form>
    </x-ui.page>

</x-app-layout>
