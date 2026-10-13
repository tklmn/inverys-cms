{{--
    System information tabs: overview and components (SBOM).
    As links — each page only loads what it shows; the bill of materials does not read
    installed.json and the plugin folders on every visit to the overview.
--}}
@php
    $systemTabs = [
        ['route' => 'backend.system.info', 'icon' => 'server', 'label' => __('translation.system.tabs.overview')],
        ['route' => 'backend.system.components', 'icon' => 'boxes', 'label' => __('translation.system.tabs.components')],
    ];
@endphp

<nav class="ui-tabs" aria-label="{{ __('translation.system.info.title') }}">
    @foreach($systemTabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            class="ui-tab"
            @if(request()->routeIs($tab['route'])) aria-selected="true" aria-current="page" @endif
        >
            <x-ui.nav-icon :name="$tab['icon']" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
