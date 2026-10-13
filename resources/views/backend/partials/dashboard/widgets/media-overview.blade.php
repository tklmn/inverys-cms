<div class="ui-card-head">
    <h3 class="ui-card-title"><span class="ui-chip ui-chip-sm ui-tint-violet"><x-ui.icon name="images" /></span>{{ __('translation.dashboard.media_overview') }}</h3>
    @permission('show-media')
        <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.media.index')">
            {{ __('translation.dashboard.view_all') }}
        </x-ui.button>
    @endpermission
</div>

<x-ui.list>
    @forelse($recentMedia as $media)
        <x-ui.list-row :title="$media->name" :href="route('backend.media.show', $media)">
            <x-slot:lead>
                @if(str_starts_with($media->mime_type, 'image/'))
                    <img src="{{ $media->thumbnail_url }}" alt="" class="h-full w-full rounded-md object-cover">
                @else
                    <x-ui.icon name="file" />
                @endif
            </x-slot:lead>

            <x-slot:meta>
                <span>{{ $media->formatted_size }}</span>
                <span>{{ $media->created_at->diffForHumans() }}</span>
            </x-slot:meta>
        </x-ui.list-row>
    @empty
        <x-ui.empty icon="image">{{ __('translation.dashboard.no_media') }}</x-ui.empty>
    @endforelse
</x-ui.list>
