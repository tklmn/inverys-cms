{{--
    Extensions → Themes / Plugins: the installed packages and the marketplace.
    $type: "theme" or "plugin". As links, like the system tabs; each tab only
    for whoever may open it — with a single tab there is nothing to switch.
--}}
@if(\App\Services\Updates\Marketplace::enabled())
    @php
        $user = auth()->user();
        $isTheme = $type === 'theme';
        $extensionTabs = array_values(array_filter([
            [
                'route' => $isTheme ? 'backend.themes.index' : 'backend.plugins.index',
                'icon' => $isTheme ? 'palette' : 'puzzle',
                'label' => __('translation.marketplace.tab_installed'),
                'allowed' => $isTheme ? $user->hasPermission('manage-themes') : ($user->hasPermission('manage-plugins') && $user->hasRole('admin')),
            ],
            [
                'route' => $isTheme ? 'backend.themes.marketplace' : 'backend.plugins.marketplace',
                'icon' => 'package',
                'label' => __('translation.marketplace.title'),
                'allowed' => $isTheme
                    ? $user->hasPermission('manage-themes') || $user->hasPermission('install-themes')
                    : ($user->hasPermission('manage-plugins') && $user->hasRole('admin')) || $user->hasPermission('install-plugins'),
            ],
        ], fn (array $tab) => $tab['allowed']));
    @endphp

    @if(count($extensionTabs) > 1)
        <nav class="ui-tabs" aria-label="{{ $isTheme ? __('Themes') : __('Plugins') }}">
            @foreach($extensionTabs as $tab)
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
    @endif
@endif
