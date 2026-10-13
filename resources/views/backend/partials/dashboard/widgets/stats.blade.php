{{-- Key figures. The card itself is the link — no separate footer. --}}
@php($tones = ['accent', 'violet', 'teal', 'positive', 'caution'])

<div class="ui-stats">
    @permission('show-page')
        <x-ui.stat
            :label="__('translation.navigation.pages')"
            :value="$pageCount"
            icon="file-text"
            tone="accent"
            :href="route('backend.pages.index')"
        />
    @endpermission

    @permission('show-media')
        <x-ui.stat
            :label="__('translation.navigation.media')"
            :value="$mediaCount"
            icon="image"
            tone="violet"
            :href="route('backend.media.index')"
        />
    @endpermission

    @permission('show-user')
        <x-ui.stat
            :label="__('translation.dashboard.users')"
            :value="$userCount"
            icon="users"
            tone="teal"
            :href="route('backend.users.index')"
        />
    @endpermission

    @foreach($statCards as $stat)
        <x-ui.stat
            :tone="$tones[($loop->index + 3) % count($tones)]"
            :label="__($stat['label'])"
            :value="$stat['count']"
            :icon="$stat['icon'] ?? 'plug'"
            :href="isset($stat['route']) ? route($stat['route']) : null"
        />
    @endforeach
</div>
