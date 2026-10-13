<div class="ui-card-head">
    <h3 class="ui-card-title"><span class="ui-chip ui-chip-sm ui-tint-teal"><x-ui.icon name="server" /></span>{{ __('translation.dashboard.system_information') }}</h3>
    @permission('show-system')
        <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.system.info')">
            {{ __('translation.dashboard.view_details') }}
        </x-ui.button>
    @endpermission
</div>

<div class="ui-card-body">
    <x-ui.facts>
        <x-ui.fact label="PHP">{{ phpversion() }}</x-ui.fact>
        <x-ui.fact label="Laravel">{{ app()->version() }}</x-ui.fact>
        <x-ui.fact label="CMS">{{ config('cms.version') }}</x-ui.fact>
        <x-ui.fact :label="__('translation.system.info.server_time')">{{ format_datetime(now()) }}</x-ui.fact>
    </x-ui.facts>
</div>
