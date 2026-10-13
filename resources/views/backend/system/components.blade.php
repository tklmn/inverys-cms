<x-app-layout>
    <x-slot name="header">{{ __('translation.system.sbom.title') }}</x-slot>

    @php
        /*
         * Bill of materials of this installation. Grouped by kind (plugins, themes,
         * libraries, development) — plugins and themes on top, because they are
         * managed by the user; the libraries ship with the CMS.
         */
        $kindIcons = [
            \App\Support\Sbom\Inventory::KIND_LIBRARY => 'package',
            \App\Support\Sbom\Inventory::KIND_DEV => 'code',
            \App\Support\Sbom\Inventory::KIND_PLUGIN => 'puzzle',
            \App\Support\Sbom\Inventory::KIND_THEME => 'palette',
        ];
        $stateTones = ['enabled' => 'positive', 'active' => 'positive', 'disabled' => 'neutral', 'inactive' => 'neutral'];

        $counts = $summary['counts'];
        $grouped = collect($components)->groupBy('kind');
        $hasFilter = $filters['kind'] !== null || $filters['dev'] || $filters['q'] !== '';
        $user = auth()->user();

        // Manifest names are English and get translated, Composer names do not
        $titleOf = fn (array $item) => $item['slug'] !== null ? __($item['name']) : $item['name'];
    @endphp

    <x-ui.page :title="__('translation.system.info.title')" :lead="__('translation.system.sbom.lead')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="download" :href="route('backend.system.sbom.download')">
                {{ __('translation.system.sbom.download') }}
            </x-ui.button>
        </x-slot:actions>

        @include('backend.system.partials.tabs')

        <div class="ui-stats">
            <x-ui.stat icon="package" tone="accent" :label="__('translation.system.info.cms_version')" :value="$summary['cms_version']" />
            <x-ui.stat
                icon="boxes"
                tone="teal"
                :label="__('translation.system.sbom.stat_components')"
                :value="$summary['runtime_total']"
                :delta="$counts['dev'] ? trans_choice('translation.system.sbom.stat_components_delta', $counts['dev'], ['count' => $counts['dev']]) : null"
            />
            <x-ui.stat
                icon="puzzle"
                tone="violet"
                :label="__('translation.system.sbom.stat_plugins')"
                :value="$summary['plugins_enabled'].' / '.$counts['plugin']"
                :href="$user->hasPermission('manage-plugins') ? route('backend.plugins.index') : null"
            />
            <x-ui.stat
                icon="palette"
                tone="caution"
                :label="__('translation.system.sbom.stat_themes')"
                :value="$counts['theme']"
                :delta="$summary['active_theme'] ? __('translation.system.sbom.stat_themes_delta', ['name' => __($summary['active_theme'])]) : null"
                :href="$user->hasPermission('manage-themes') ? route('backend.themes.index') : null"
            />
        </div>

        <x-ui.note tone="neutral" icon="info" :title="__('translation.system.sbom.source_title')">
            {{ __('translation.system.sbom.source_text') }}
        </x-ui.note>

        <x-ui.card flush>
            {{-- Kind and dev packages via the URL, the search additionally instant (ui/list-filter.js) --}}
            <x-ui.toolbar :data-filter-label="__('translation.system.sbom.filter_label')">
                <form action="{{ route('backend.system.components') }}" method="GET" class="flex flex-1 flex-wrap items-center gap-2">
                    <x-ui.input
                        icon="search"
                        type="search"
                        name="q"
                        value="{{ $filters['q'] }}"
                        class="w-full sm:w-72"
                        data-filter-input
                        :placeholder="__('translation.system.sbom.filter_placeholder')"
                        :aria-label="__('translation.filter')"
                    />

                    <x-ui.select name="kind" class="w-auto" :aria-label="__('translation.system.sbom.kind')" data-autosubmit>
                        <option value="">{{ __('translation.system.sbom.all_kinds') }}</option>
                        @foreach(\App\Support\Sbom\Inventory::KINDS as $kind)
                            <option value="{{ $kind }}" @selected($filters['kind'] === $kind) @disabled($counts[$kind] === 0)>
                                {{ __('translation.system.sbom.groups.'.$kind) }} ({{ $counts[$kind] }})
                            </option>
                        @endforeach
                    </x-ui.select>

                    {{-- Without dev packages (--no-dev) disabled instead of hidden --}}
                    <span class="inline-flex" title="{{ $counts['dev'] === 0 ? __('translation.system.sbom.no_dev') : '' }}">
                        <x-ui.toggle
                            name="dev"
                            on-value="1"
                            off-value="0"
                            :checked="$filters['dev']"
                            :disabled="$counts['dev'] === 0"
                            :label="__('translation.system.sbom.include_dev').' ('.$counts['dev'].')'"
                            data-autosubmit
                        />
                    </span>

                    @if($hasFilter)
                        <x-ui.button variant="ghost" icon="x" :href="route('backend.system.components')">{{ __('translation.clear') }}</x-ui.button>
                    @endif
                </form>

                <span class="ui-help" data-filter-count>
                    {{ trans_choice('translation.system.sbom.count', count($components), ['count' => count($components)]) }}
                </span>
            </x-ui.toolbar>

            <x-ui.list data-filter-scope>
                @forelse($grouped as $kind => $items)
                    <x-ui.list-group data-filter-group>
                        <span class="flex items-center gap-1.5">
                            <x-ui.icon :name="$kindIcons[$kind]" />
                            {{ __('translation.system.sbom.groups.'.$kind) }}
                            <span class="ui-tab-count">{{ $items->count() }}</span>
                        </span>
                    </x-ui.list-group>

                    @foreach($items as $item)
                        @php
                            $isExtension = $item['slug'] !== null;
                            $kindLabel = __('translation.system.sbom.kinds.'.$kind);
                            $stateLabel = $item['state'] ? __('translation.system.sbom.states.'.$item['state']) : null;
                        @endphp

                        <x-ui.list-row
                            :title="$titleOf($item)"
                            :icon="$kindIcons[$kind]"
                            data-filter-item="{{ \App\Support\Sbom\Inventory::searchText($item) }}"
                        >
                            <x-slot:meta>
                                <span class="font-mono">{{ $item['version'] }}</span>
                                @if($isExtension)
                                    <span class="font-mono">{{ $item['composer'] ? $item['package'] : $item['path'] }}</span>
                                @endif
                                @if($item['description'] !== '')
                                    <span>{{ Str::limit($isExtension ? __($item['description']) : $item['description'], 90) }}</span>
                                @endif
                            </x-slot:meta>

                            <x-slot:cols>
                                {{-- max-w-full: multiple licences (nette/*: three) wrap when narrow instead of overflowing --}}
                                <x-ui.list-col :label="__('translation.system.sbom.license')" class="max-w-full">
                                    <span class="flex flex-wrap gap-1">
                                        @forelse($item['licenses'] as $license)
                                            <x-ui.badge :dot="false">{{ $license }}</x-ui.badge>
                                        @empty
                                            <x-ui.badge tone="caution" :dot="false">{{ __('translation.system.sbom.no_license') }}</x-ui.badge>
                                        @endforelse
                                    </span>
                                </x-ui.list-col>

                                <x-ui.list-col :label="__('translation.system.sbom.kind')" priority="low">
                                    <span class="flex flex-wrap gap-1">
                                        <x-ui.badge :tone="$isExtension ? 'accent' : 'neutral'" :dot="false">{{ $kindLabel }}</x-ui.badge>
                                        @if($stateLabel)
                                            <x-ui.badge :tone="$stateTones[$item['state']]">{{ $stateLabel }}</x-ui.badge>
                                        @endif
                                    </span>
                                </x-ui.list-col>
                            </x-slot:cols>

                            <x-slot:actions>
                                {{-- Disabled without a source URL so all rows have the same layout --}}
                                <x-ui.button
                                    variant="ghost"
                                    icon="external-link"
                                    :label="__('translation.system.sbom.open_source')"
                                    :href="$item['url']"
                                    :target="$item['url'] ? '_blank' : null"
                                    :rel="$item['url'] ? 'noopener noreferrer' : null"
                                    :disabled="$item['url'] === null"
                                    :disabled-reason="__('translation.system.sbom.no_source')"
                                />
                            </x-slot:actions>

                            <x-slot:detail>
                                <x-ui.facts class="mb-4">
                                    <x-ui.fact :label="__('translation.system.sbom.package')">
                                        <code class="font-mono text-xs break-all">{{ $item['package'] }}</code>
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.system.sbom.version')">
                                        <code class="font-mono text-xs">{{ $item['version'] }}</code>
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.system.sbom.kind')">
                                        {{ $kindLabel }}@if($stateLabel) · {{ $stateLabel }}@endif
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.system.sbom.license')">
                                        {{ $item['licenses'] ? implode(', ', $item['licenses']) : __('translation.system.sbom.no_license') }}
                                    </x-ui.fact>
                                    <x-ui.fact :label="__('translation.system.sbom.installed_via')">
                                        {{ $item['composer'] ? __('translation.system.sbom.via_composer') : __('translation.system.sbom.via_folder') }}
                                    </x-ui.fact>
                                    @if($item['path'])
                                        <x-ui.fact :label="__('translation.system.sbom.path')">
                                            <code class="font-mono text-xs break-all">{{ $item['path'] }}</code>
                                        </x-ui.fact>
                                    @endif
                                    @if($item['purl'])
                                        <x-ui.fact :label="__('translation.system.sbom.purl')">
                                            <code class="font-mono text-xs break-all">{{ $item['purl'] }}</code>
                                        </x-ui.fact>
                                    @endif
                                    @if($item['commit'])
                                        <x-ui.fact :label="__('translation.system.sbom.commit')">
                                            <code class="font-mono text-xs">{{ Str::limit($item['commit'], 12, '') }}</code>
                                        </x-ui.fact>
                                    @endif
                                </x-ui.facts>

                                @if($item['description'] !== '')
                                    <p class="ui-section-label mb-1.5">{{ __('translation.system.sbom.description') }}</p>
                                    <p class="text-sm text-fg-muted">{{ $isExtension ? __($item['description']) : $item['description'] }}</p>
                                @endif

                                @if($item['url'])
                                    <div data-panel-actions>
                                        <x-ui.button variant="secondary" icon="external-link" :href="$item['url']" target="_blank" rel="noopener noreferrer">
                                            {{ __('translation.system.sbom.open_source') }}
                                        </x-ui.button>
                                    </div>
                                @endif
                            </x-slot:detail>
                        </x-ui.list-row>
                    @endforeach
                @empty
                    @if($hasFilter)
                        <x-ui.empty icon="search" :title="__('translation.system.sbom.none_found')">{{ __('translation.no_filter_results') }}</x-ui.empty>
                    @else
                        <x-ui.empty icon="boxes" :title="__('translation.system.sbom.none_found')">{{ __('translation.system.sbom.none_found_hint') }}</x-ui.empty>
                    @endif
                @endforelse

                <div class="ui-empty hidden" data-filter-empty>
                    <span class="ui-empty-icon"><x-ui.icon name="search" /></span>
                    <p class="ui-empty-text">{{ __('translation.no_results') }}</p>
                </div>
            </x-ui.list>
        </x-ui.card>

        <div class="ui-grid">
            <x-ui.card :title="__('translation.system.sbom.platform_title')" icon="cpu" tone="violet">
                <x-ui.facts class="grid-cols-1!">
                    <x-ui.fact :label="__('translation.system.info.php_version')">{{ $summary['php_version'] }}</x-ui.fact>
                    <x-ui.fact :label="__('translation.system.sbom.php_sapi')">{{ $summary['php_sapi'] }}</x-ui.fact>
                    <x-ui.fact :label="__('translation.system.info.php_extensions')">{{ $summary['php_extensions'] }}</x-ui.fact>
                    <x-ui.fact :label="__('translation.system.sbom.composer_mode')">
                        {{ $summary['dev_mode'] ? __('translation.system.sbom.composer_with_dev') : __('translation.system.sbom.composer_no_dev') }}
                    </x-ui.fact>
                    <x-ui.fact :label="__('translation.system.sbom.generated')">{{ format_datetime($generatedAt, true) }}</x-ui.fact>
                </x-ui.facts>

                <x-slot:foot>
                    <p class="ui-help">{{ __('translation.system.sbom.download_hint') }}</p>
                </x-slot:foot>
            </x-ui.card>

            <x-ui.card :title="__('translation.system.sbom.release_title')" icon="file-json" tone="teal" flush>
                <x-ui.list>
                    @foreach($releases as $key => $release)
                        @php
                            $releaseTitle = __('translation.system.sbom.release_'.$key);
                            [$badgeTone, $badgeText] = match (true) {
                                ! $release['exists'] => ['neutral', __('translation.system.sbom.release_missing')],
                                $release['matches'] => ['positive', __('translation.system.sbom.release_matches')],
                                default => ['caution', __('translation.system.sbom.release_other')],
                            };
                        @endphp

                        <x-ui.list-row :title="$releaseTitle" icon="file-json">
                            <x-slot:meta>
                                <span class="font-mono">{{ $release['file'] }}</span>
                                @if($release['exists'])
                                    @if($release['version'])<span>{{ __('translation.system.sbom.release_version', ['version' => $release['version']]) }}</span>@endif
                                    <span>{{ trans_choice('translation.system.sbom.count', $release['count'], ['count' => $release['count']]) }}</span>
                                    @if($release['timestamp'])<span>{{ format_datetime($release['timestamp']) }}</span>@endif
                                @endif
                                @if($key === 'npm')<span>{{ __('translation.system.sbom.release_npm_hint') }}</span>@endif
                            </x-slot:meta>

                            <x-slot:cols>
                                <x-ui.list-col>
                                    <x-ui.badge :tone="$badgeTone">{{ $badgeText }}</x-ui.badge>
                                </x-ui.list-col>
                            </x-slot:cols>

                            <x-slot:actions>
                                <x-ui.button
                                    variant="ghost"
                                    icon="download"
                                    :label="__('translation.system.sbom.release_download')"
                                    :href="$release['exists'] ? route('backend.system.sbom.release', $key) : null"
                                    :disabled="! $release['exists']"
                                    :disabled-reason="__('translation.system.sbom.release_missing')"
                                />
                            </x-slot:actions>
                        </x-ui.list-row>
                    @endforeach
                </x-ui.list>

                <x-slot:foot>
                    <p class="ui-help">{{ __('translation.system.sbom.release_lead') }}</p>
                </x-slot:foot>
            </x-ui.card>
        </div>
    </x-ui.page>
</x-app-layout>
