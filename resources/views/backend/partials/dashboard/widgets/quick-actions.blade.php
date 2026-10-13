<div class="ui-card-head">
    <h3 class="ui-card-title"><span class="ui-chip ui-chip-sm ui-tint-caution"><x-ui.icon name="zap" /></span>{{ __('translation.dashboard.quick_actions') }}</h3>
</div>

<x-ui.list>
    @permission('create-page')
        <x-ui.list-row :title="__('translation.dashboard.create_page')" icon="file-text" :href="route('backend.pages.create')">
            <x-slot:actions>
                <x-ui.icon name="chevron-right" class="text-fg-subtle" />
            </x-slot:actions>
        </x-ui.list-row>
    @endpermission

    @permission('create-media')
        <x-ui.list-row :title="__('translation.dashboard.upload_media')" icon="upload" :href="route('backend.media.create')">
            <x-slot:actions>
                <x-ui.icon name="chevron-right" class="text-fg-subtle" />
            </x-slot:actions>
        </x-ui.list-row>
    @endpermission

    @foreach($quickActions as $action)
        <x-ui.list-row :title="__($action['label'])" :icon="$action['icon'] ?? 'plug'" :href="route($action['route'])">
            <x-slot:actions>
                <x-ui.icon name="chevron-right" class="text-fg-subtle" />
            </x-slot:actions>
        </x-ui.list-row>
    @endforeach

    <x-ui.list-row :title="__('translation.dashboard.view_site')" icon="external-link" :href="url('/')">
        <x-slot:actions>
            <x-ui.icon name="chevron-right" class="text-fg-subtle" />
        </x-slot:actions>
    </x-ui.list-row>
</x-ui.list>
