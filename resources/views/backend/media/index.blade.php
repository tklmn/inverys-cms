<x-app-layout>
    <x-slot name="header">{{ __('translation.media.title') }}</x-slot>

    @php
        // File type → icon. Media tiles carry no colour, otherwise the
        // grid gets more colourful than the thumbnails themselves.
        $typeIcons = [
            'pdf' => 'file-text',
            'doc' => 'file-text',
            'xls' => 'file-text',
            'ppt' => 'file-text',
            'video' => 'file',
            'audio' => 'file',
        ];
    @endphp

    <x-ui.page :title="__('translation.media.title')">
        <x-slot:actions>
            @permission('create-media')
                <x-ui.button variant="primary" size="md" icon="upload" :href="route('backend.media.create')">
                    {{ __('translation.media.upload_new') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form action="{{ route('backend.media.index') }}" method="GET" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        id="search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-64"
                        :placeholder="__('translation.search_placeholder')"
                        :aria-label="__('translation.search')"
                    />

                    <x-ui.select name="type" id="type" class="w-auto" :aria-label="__('translation.media.type')">
                        <option value="">{{ __('translation.all') }}</option>
                        <option value="image" @selected(request('type') == 'image')>{{ __('translation.media.images') }}</option>
                        <option value="document" @selected(request('type') == 'document')>{{ __('translation.media.documents') }}</option>
                        <option value="video" @selected(request('type') == 'video')>{{ __('translation.media.videos') }}</option>
                        <option value="audio" @selected(request('type') == 'audio')>{{ __('translation.media.audio') }}</option>
                    </x-ui.select>

                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button>

                    @if(request()->hasAny(['search', 'type']))
                        <x-ui.button variant="ghost" icon="x" :href="route('backend.media.index')">{{ __('translation.clear') }}</x-ui.button>
                    @endif
                </form>
            </x-ui.toolbar>

            @if($media->isEmpty() && (request()->filled('search') || request()->filled('type')))
                {{-- With a filter: do not prompt for the "first upload" --}}
                <x-ui.empty icon="search" :title="__('translation.media.no_files')">{{ __('translation.no_filter_results') }}</x-ui.empty>
            @elseif($media->isEmpty())
                <x-ui.empty icon="image" :title="__('translation.media.no_files')">
                    {{ __('translation.media.upload_first') }}
                    <x-slot:actions>
                        @permission('create-media')
                            <x-ui.button variant="primary" size="md" icon="upload" :href="route('backend.media.create')">
                                {{ __('translation.media.upload_new') }}
                            </x-ui.button>
                        @endpermission
                    </x-slot:actions>
                </x-ui.empty>
            @else
                <div class="ui-tiles">
                    @foreach($media as $item)
                        <x-ui.tile
                            :title="$item->name"
                            :href="route('backend.media.show', $item)"
                            :image="str_contains($item->mime_type, 'image') ? $item->thumbnail_url : null"
                            :alt="$item->alt_text"
                            :icon="$typeIcons[$item->type] ?? 'file'"
                        >
                            <x-slot:meta>
                                <span class="uppercase">{{ pathinfo($item->file_name, PATHINFO_EXTENSION) }}</span>
                                <span>{{ $item->formatted_size }}</span>
                                <x-ui.translation-status :model="$item" />
                            </x-slot:meta>

                            <x-slot:actions>
                                @permission('edit-media')
                                    <x-ui.button variant="ghost" size="xs" icon="pencil" :label="__('translation.edit')" :href="route('backend.media.edit', $item)" />
                                @endpermission

                                @permission('delete-media')
                                    <form id="delete-form-media-{{ $item->id }}" action="{{ route('backend.media.destroy', $item) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            variant="danger"
                                            size="xs"
                                            icon="trash-2"
                                            :label="__('translation.delete')"
                                            data-delete-modal="{{ json_encode(['formId' => 'delete-form-media-'.$item->id]) }}"
                                        />
                                    </form>
                                @endpermission
                            </x-slot:actions>
                        </x-ui.tile>
                    @endforeach
                </div>

                @if($media->hasPages())
                    <x-slot:foot>{{ $media->links() }}</x-slot:foot>
                @endif
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
