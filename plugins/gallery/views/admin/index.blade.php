<x-app-layout>
    <x-slot name="header">{{ __('gallery::gallery.albums') }}</x-slot>

    <x-ui.page :title="__('gallery::gallery.albums')">
        <x-slot:actions>
            @permission('create-gallery-album')
                <x-ui.button variant="primary" size="md" icon="plus" :href="route('backend.gallery.create')">
                    {{ __('gallery::gallery.create_album') }}
                </x-ui.button>
            @endpermission
        </x-slot:actions>

        <x-ui.card flush>
            <x-ui.toolbar>
                <form method="GET" action="{{ route('backend.gallery.index') }}" class="flex w-full flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="w-full sm:w-64"
                        :placeholder="__('gallery::gallery.search_albums')"
                        :aria-label="__('translation.search')"
                    />
                    <x-ui.button variant="secondary" type="submit" icon="filter">{{ __('translation.filter') }}</x-ui.button>
                </form>
            </x-ui.toolbar>

            <x-ui.list>
                @forelse($albums as $album)
                    @php($cover = $album->images->first())

                    <x-ui.list-row
                        :title="$album->name"
                        :href="auth()->user()->hasPermission('edit-gallery-album') ? route('backend.gallery.edit', $album) : null"
                    >
                        {{-- First image as the cover, otherwise the album icon --}}
                        <x-slot:lead>
                            @if($cover)
                                <img src="{{ $cover->thumbnail_url }}" alt="" loading="lazy" class="h-full w-full rounded-md object-cover">
                            @else
                                <x-ui.nav-icon name="images" />
                            @endif
                        </x-slot:lead>

                        <x-slot:meta>
                            <span>/{{ $album->slug }}</span>
                            <x-ui.translation-status :model="$album" />
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('gallery::gallery.images')" priority="low">
                                {{ $album->images_count ?? 0 }}
                            </x-ui.list-col>

                            <x-ui.list-col>
                                @if($album->is_active)
                                    <x-ui.badge tone="positive">{{ __('gallery::gallery.active') }}</x-ui.badge>
                                @else
                                    <x-ui.badge>{{ __('gallery::gallery.inactive') }}</x-ui.badge>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @permission('edit-gallery-album')
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="route('backend.gallery.edit', $album)" />
                            @endpermission

                            @permission('delete-gallery-album')
                                <form id="delete-album-{{ $album->id }}" action="{{ route('backend.gallery.destroy', $album) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('translation.delete')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-album-'.$album->id]) }}"
                                    />
                                </form>
                            @endpermission
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="images">{{ __('gallery::gallery.no_albums') }}</x-ui.empty>
                @endforelse
            </x-ui.list>

            @if($albums->hasPages())
                <x-slot:foot>{{ $albums->links() }}</x-slot:foot>
            @endif
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
