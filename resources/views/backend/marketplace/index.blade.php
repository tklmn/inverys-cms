{{--
    Marketplace: themes or plugins from the catalogs of the marketplace
    sources (App\Services\Updates\Marketplace). Install and update here, then
    activate a theme right on its card; plugins are switched on in their list.
--}}
@php
    $isTheme = $type === 'theme';
    $title = $isTheme ? __('Themes') : __('Plugins');
    $hasSources = $configuredSources !== [] || $addedSources !== [];
    $canActivate = $isTheme && auth()->user()->hasPermission('manage-themes');
@endphp

<x-app-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <x-ui.page :title="$title" :lead="$hasSources ? ($isTheme ? __('translation.marketplace.lead_themes') : __('translation.marketplace.lead_plugins')) : null">
        @if($enabled)
            <x-slot:actions>
                @if($hasSources)
                    <form action="{{ route($isTheme ? 'backend.themes.marketplace.refresh' : 'backend.plugins.marketplace.refresh') }}" method="POST">
                        @csrf
                        <x-ui.button size="md" icon="refresh-cw" type="submit" :title="$fetchedAt ? __('translation.marketplace.fetched_at', ['time' => format_datetime($fetchedAt, true)]) : null">{{ __('translation.marketplace.refresh') }}</x-ui.button>
                    </form>
                @endif
                @if($canManageSources)
                    <x-ui.button size="md" icon="sliders-horizontal" data-csp="marketplace-sources-toggle">{{ __('translation.marketplace.sources') }}</x-ui.button>
                @endif
            </x-slot:actions>
        @endif

        @include('backend.marketplace.partials.tabs', ['type' => $type])

        @if(! $enabled)
            <x-ui.note :title="__('translation.marketplace.disabled_title')">{{ __('translation.marketplace.disabled') }}</x-ui.note>
        @else
            @if($canManageSources)
                {{-- Sources: catalog addresses from the .env (fixed) and the ones added here; open while there are none or after an error --}}
                <div id="marketplace-sources" @class(['hidden' => $hasSources && ! $errors->has('sources')])>
                    <x-ui.card>
                        <form action="{{ route($isTheme ? 'backend.themes.marketplace.sources' : 'backend.plugins.marketplace.sources') }}" method="POST" class="flex flex-col gap-3">
                            @csrf
                            <div>
                                <h2 class="ui-card-title">{{ __('translation.marketplace.sources') }}</h2>
                                <p class="ui-help">{{ __('translation.marketplace.sources_lead') }}</p>
                            </div>
                            @if($configuredSources !== [])
                                <div class="flex flex-col gap-1 text-sm">
                                    <span class="ui-help">{{ __('translation.marketplace.sources_fixed') }}</span>
                                    @foreach($configuredSources as $source)
                                        <code class="break-all text-xs">{{ $source }}</code>
                                    @endforeach
                                </div>
                            @endif
                            <x-ui.field name="sources" :label="__('translation.marketplace.sources_added')" :help="__('translation.marketplace.sources_help')">
                                <x-ui.textarea id="sources" name="sources" rows="3" :placeholder="__('translation.marketplace.sources_placeholder')" spellcheck="false">{{ old('sources', implode("\n", $addedSources)) }}</x-ui.textarea>
                            </x-ui.field>
                            <div>
                                <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>
                </div>
            @endif

            @if(! $hasSources)
                <x-ui.note :title="__('translation.marketplace.no_sources_title')">{{ $canManageSources ? __('translation.marketplace.no_sources') : __('translation.marketplace.no_sources_ask') }}</x-ui.note>
            @endif

            @foreach($catalogErrors as $source => $message)
                <x-ui.note tone="caution" :title="__('translation.marketplace.error_title', ['source' => parse_url($source, PHP_URL_HOST)])">{{ $message }}</x-ui.note>
            @endforeach

            @if($hasSources)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($items as $item)
                    @php
                        $shot = $item['screenshot'] ? \App\Services\Updates\Marketplace::screenshotDir().'/'.basename($item['screenshot']) : null;
                        $hasShot = $shot && is_file($shot);
                        $mime = $hasShot ? match (pathinfo($shot, PATHINFO_EXTENSION)) { 'jpg' => 'image/jpeg', 'webp' => 'image/webp', default => 'image/png' } : null;
                        $action = fn (string $what) => route(($isTheme ? 'backend.themes.marketplace.' : 'backend.plugins.marketplace.').$what);
                    @endphp

                    <div class="ui-card overflow-hidden {{ $item['active'] ? 'border-accent' : '' }}">
                        <div class="flex h-36 items-center justify-center bg-surface-sunken">
                            @if($hasShot)
                                <img src="data:{{ $mime }};base64,{{ base64_encode((string) file_get_contents($shot)) }}" alt="{{ $item['name'] }}" class="h-full w-full object-cover object-top">
                            @else
                                <x-ui.icon :name="$isTheme ? 'palette' : 'puzzle'" class="text-3xl text-fg-subtle" />
                            @endif
                        </div>

                        <div class="ui-card-body">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <h3 class="ui-card-title">{{ $item['name'] }}</h3>
                                <span class="ui-help">v{{ $item['version'] }}</span>
                            </div>

                            <p class="ui-help">{{ $item['description'] }}</p>
                            <p class="mt-1 text-xs text-fg-muted">
                                @if($item['author'] !== ''){{ __('By') }} {{ $item['author'] }} · @endif
                                {{ __('translation.marketplace.from_source', ['source' => $item['source']]) }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @switch($item['state'])
                                    @case('available')
                                        @if($installBlocked === null)
                                            <form action="{{ $action('install') }}" method="POST"
                                                  data-confirm="{{ __('translation.marketplace.confirm_install', ['name' => $item['name'], 'version' => $item['version'], 'source' => $item['source']]) }}">
                                                @csrf
                                                <input type="hidden" name="slug" value="{{ $item['slug'] }}">
                                                <x-ui.button variant="primary" icon="download" type="submit">{{ __('translation.install') }}</x-ui.button>
                                            </form>
                                        @else
                                            <x-ui.button variant="primary" icon="download" :disabled="true" :disabled-reason="$installBlocked">{{ __('translation.install') }}</x-ui.button>
                                        @endif
                                        @break

                                    @case('incompatible')
                                        <x-ui.badge tone="caution">{{ __('translation.marketplace.state_incompatible', ['requires' => $item['requires']]) }}</x-ui.badge>
                                        @break

                                    @default
                                        <span @if($item['state'] === 'foreign') title="{{ __('translation.marketplace.state_foreign') }}" @endif>
                                            <x-ui.badge tone="positive">{{ __('translation.marketplace.state_installed', ['version' => $item['installed']]) }}</x-ui.badge>
                                        </span>
                                        @if($item['active'])
                                            <x-ui.badge tone="accent">{{ $isTheme ? __('Active') : __('Enabled') }}</x-ui.badge>
                                        @elseif($canActivate)
                                            {{-- A theme is switched on right here; plugins are enabled in their list (install routine, toggle) --}}
                                            <form action="{{ route('backend.themes.activate') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="slug" value="{{ $item['slug'] }}">
                                                <x-ui.button variant="secondary" icon="check" type="submit">{{ __('Activate') }}</x-ui.button>
                                            </form>
                                        @endif
                                        @if($item['state'] === 'update')
                                            @if($installBlocked === null)
                                                <form action="{{ $action('update') }}" method="POST"
                                                      data-confirm="{{ __('translation.updates.confirm_update', ['name' => $item['name'], 'version' => $item['version']]) }}">
                                                    @csrf
                                                    <input type="hidden" name="slug" value="{{ $item['slug'] }}">
                                                    <x-ui.button icon="download" type="submit">{{ __('translation.updates.update_to', ['version' => $item['version']]) }}</x-ui.button>
                                                </form>
                                            @else
                                                <x-ui.button icon="download" :disabled="true" :disabled-reason="$installBlocked">{{ __('translation.updates.update_to', ['version' => $item['version']]) }}</x-ui.button>
                                            @endif
                                        @endif
                                @endswitch

                                @if($item['homepage'])
                                    <span class="ms-auto">
                                        <x-ui.button variant="ghost" icon="external-link" :href="$item['homepage']" target="_blank" rel="noopener noreferrer" :label="__('translation.marketplace.homepage')" />
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="sm:col-span-2 xl:col-span-3">
                        <x-ui.card>
                            <x-ui.empty icon="package">{{ $isTheme ? __('translation.marketplace.empty_themes') : __('translation.marketplace.empty_plugins') }}</x-ui.empty>
                        </x-ui.card>
                    </div>
                @endforelse
            </div>
            @endif
        @endif
    </x-ui.page>

    @if($canManageSources)
        <script @cspNonce>
            document.querySelectorAll('[data-csp="marketplace-sources-toggle"]').forEach(function (el) {
                el.addEventListener('click', function () {
                    document.getElementById('marketplace-sources').classList.toggle('hidden');
                });
            });
        </script>
    @endif
</x-app-layout>
