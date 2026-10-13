<div class="ui-card-head">
    <h3 class="ui-card-title"><span class="ui-chip ui-chip-sm ui-tint-accent"><x-ui.icon name="file-text" /></span>{{ __('translation.dashboard.recent_pages') }}</h3>
    @permission('show-page')
        <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.pages.index')">
            {{ __('translation.dashboard.view_all') }}
        </x-ui.button>
    @endpermission
</div>

<x-ui.list>
    @forelse($recentPages as $page)
        <x-ui.list-row :title="$page->title" icon="file-text" :href="route('backend.pages.edit', $page)">
            <x-slot:meta>
                <span>{{ $page->updated_at->diffForHumans() }}</span>
            </x-slot:meta>
            <x-slot:cols>
                <x-ui.list-col>
                    @if($page->is_active)
                        <x-ui.badge tone="positive">{{ __('translation.pages.active') }}</x-ui.badge>
                    @else
                        <x-ui.badge>{{ __('translation.pages.inactive') }}</x-ui.badge>
                    @endif
                </x-ui.list-col>
            </x-slot:cols>
        </x-ui.list-row>
    @empty
        <x-ui.empty icon="file-text">{{ __('translation.pages.no_pages_found') }}</x-ui.empty>
    @endforelse
</x-ui.list>
