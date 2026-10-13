<x-app-layout>
    <x-slot name="header">{{ __('translation.seo.title') }}</x-slot>

    <x-ui.page :title="__('translation.seo.title')" :lead="__('translation.seo.lead')">
        <x-slot:actions>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="seo-settings">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        {{-- Saved state: blocked search engines are easy to overlook before going live --}}
        @unless($settings['robots_allow_indexing'])
            <x-ui.note tone="critical" icon="shield-off" class="mb-4">{{ __('translation.seo.indexing_blocked') }}</x-ui.note>
        @endunless

        {{-- Two columns from xl: sitemap on the left, robots.txt on the right; search engines above --}}
        <form id="seo-settings" method="POST" action="{{ route('backend.settings.seo.save') }}" class="grid grid-cols-[minmax(0,1fr)] items-start gap-4 xl:grid-cols-2">
            @csrf

            <x-ui.card :title="__('translation.seo.indexing')" icon="search-x" tone="critical" class="xl:col-span-2">
                <x-ui.switch-row
                    name="robots_allow_indexing"
                    :label="__('translation.seo.robots_allow')"
                    :hint="__('translation.seo.robots_allow_hint')"
                    :checked="old('robots_allow_indexing', $settings['robots_allow_indexing'] ? 'on' : 'off') === 'on'"
                />
            </x-ui.card>

            <div class="flex flex-col gap-4">
                <x-ui.card :title="__('translation.seo.sitemap')" icon="layers" tone="accent">
                    <x-slot:head>
                        <x-ui.button variant="ghost" icon="external-link" :href="$sitemapUrl" target="_blank" rel="noopener noreferrer">{{ __('translation.seo.open') }}</x-ui.button>
                    </x-slot:head>

                    <div class="flex flex-col gap-4">
                        <div>
                            <x-ui.switch-row
                                name="sitemap_enabled"
                                :label="__('translation.seo.sitemap_enabled')"
                                :hint="__($isIndex ? 'translation.seo.sitemap_multilingual_hint' : 'translation.seo.sitemap_enabled_hint', ['url' => $sitemapUrl])"
                                :checked="old('sitemap_enabled', $settings['sitemap_enabled'] ? 'on' : 'off') === 'on'"
                            />
                        </div>

                        {{-- Content and cache only make sense while the sitemap exists --}}
                        <div data-requires="sitemap_enabled" class="flex flex-col gap-4">
                            <div>
                                <p class="ui-help mb-2">{{ __('translation.seo.sitemap_content') }}</p>

                                <div class="ui-choices">
                                    {{-- Pages are always included: switch for display only --}}
                                    <x-ui.switch-row
                                        name="sitemap_include_pages"
                                        :bleed="false"
                                        :label="__('translation.seo.sitemap_pages')"
                                        :hint="__('translation.seo.sitemap_pages_hint')"
                                        :checked="true"
                                        :disabled="true"
                                    />

                                    @foreach($providers as $key => $provider)
                                        <x-ui.switch-row
                                            name="sitemap_include_{{ $key }}"
                                            :bleed="false"
                                            :label="$provider['label']"
                                            :hint="$provider['hint']"
                                            :checked="old('sitemap_include_'.$key, $provider['enabled'] ? 'on' : 'off') === 'on'"
                                        />
                                    @endforeach
                                </div>
                            </div>

                            <x-ui.field name="sitemap_cache_ttl" :label="__('translation.seo.sitemap_cache')" :help="__('translation.seo.sitemap_cache_hint')">
                                <x-ui.select id="sitemap_cache_ttl" name="sitemap_cache_ttl">
                                    @foreach($cacheOptions as $minutes => $optionLabel)
                                        <option value="{{ $minutes }}" @selected((int) old('sitemap_cache_ttl', $settings['sitemap_cache_ttl']) === $minutes)>{{ $optionLabel }}</option>
                                    @endforeach
                                </x-ui.select>
                            </x-ui.field>
                        </div>
                    </div>
                </x-ui.card>

                {{-- How search engines find the sitemap: robots.txt and a one-time submission --}}
                <x-ui.card :title="__('translation.seo.discover')" icon="send" tone="teal">
                    <div class="flex flex-col gap-4" data-requires="sitemap_enabled">
                        <p class="ui-help">{{ __('translation.seo.discover_hint') }}</p>

                        <x-ui.field name="sitemap_url_copy" :label="__('translation.seo.sitemap_url')">
                            <div class="flex gap-2">
                                <x-ui.input id="sitemap_url_copy" :value="$sitemapUrl" readonly class="font-mono" />
                                <x-ui.button icon="copy" data-copy="#sitemap_url_copy">{{ __('translation.seo.copy') }}</x-ui.button>
                            </div>
                        </x-ui.field>

                        <div class="flex flex-wrap gap-2">
                            <x-ui.button variant="ghost" icon="external-link" :href="$searchConsoleUrl" target="_blank" rel="noopener noreferrer">{{ __('translation.seo.google_search_console') }}</x-ui.button>
                            <x-ui.button variant="ghost" icon="external-link" :href="$bingWebmasterUrl" target="_blank" rel="noopener noreferrer">{{ __('translation.seo.bing_webmaster_tools') }}</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="flex flex-col gap-4">
                <x-ui.card :title="__('translation.seo.robots')" icon="file-code" tone="violet">
                    <x-slot:head>
                        <x-ui.button variant="ghost" icon="external-link" :href="route('robots')" target="_blank" rel="noopener noreferrer">{{ __('translation.seo.open') }}</x-ui.button>
                    </x-slot:head>

                    <div class="flex flex-col gap-4">
                        @if($staticRobotsFile)
                            <x-ui.note tone="caution">{{ __('translation.seo.robots_static_file') }}</x-ui.note>
                        @endif

                        @unless($settings['robots_allow_indexing'])
                            <x-ui.note tone="caution">{{ __('translation.seo.robots_blocked_note') }}</x-ui.note>
                        @endunless

                        {{-- While search engines are blocked, "Disallow: /" always applies — grey out the fields --}}
                        <div data-requires="robots_allow_indexing" class="flex flex-col gap-4">
                            <div>
                                <p class="ui-help mb-2">{{ __('translation.seo.robots_mode') }}</p>

                                <div class="ui-grid ui-grid-dense">
                                    @foreach($robotsModes as $mode => $meta)
                                        <x-ui.option-card
                                            name="robots_mode"
                                            :value="$mode"
                                            :title="__('translation.seo.robots_mode_'.$mode)"
                                            :description="__('translation.seo.robots_mode_'.$mode.'_hint')"
                                            :icon="$meta['icon']"
                                            :tone="$meta['tone']"
                                            :checked="old('robots_mode', $settings['robots_mode']) === $mode"
                                        />
                                    @endforeach
                                </div>
                            </div>

                            <div data-requires="robots_mode=auto">
                                <x-ui.field name="robots_extra" :label="__('translation.seo.robots_extra')" :help="__('translation.seo.robots_extra_hint')" optional>
                                    <x-ui.textarea id="robots_extra" name="robots_extra" rows="5" class="font-mono" spellcheck="false" :placeholder="'User-agent: *'.PHP_EOL.'Disallow: /private/'">{{ old('robots_extra', $settings['robots_extra']) }}</x-ui.textarea>
                                </x-ui.field>
                            </div>

                            <div data-requires="robots_mode=custom">
                                <x-ui.field name="robots_custom" :label="__('translation.seo.robots_custom')" :help="__('translation.seo.robots_custom_hint')" optional>
                                    <x-ui.textarea id="robots_custom" name="robots_custom" rows="8" class="font-mono" spellcheck="false" :placeholder="'User-agent: *'.PHP_EOL.'Disallow: /private/'.PHP_EOL.'Allow: /'">{{ old('robots_custom', $settings['robots_custom']) }}</x-ui.textarea>
                                </x-ui.field>
                            </div>

                            {{-- Without robots.txt there is no line the sitemap could go into --}}
                            <div data-requires="!robots_mode=off">
                                <x-ui.switch-row
                                    name="robots_sitemap_line"
                                    :label="__('translation.seo.robots_sitemap_line')"
                                    :hint="__('translation.seo.robots_sitemap_line_hint', ['url' => $sitemapUrl])"
                                    :checked="old('robots_sitemap_line', $settings['robots_sitemap_line'] ? 'on' : 'off') === 'on'"
                                />
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card :title="__('translation.seo.robots_preview')" icon="eye" tone="teal" flush>
                    @if($robotsPreview === null)
                        <p class="ui-help px-4 py-3">{{ __('translation.seo.robots_off_preview', ['url' => route('robots')]) }}</p>
                    @else
                        <p class="ui-help px-4 pt-3 wrap-anywhere">{{ __('translation.seo.robots_preview_hint', ['url' => route('robots')]) }}</p>
                        <pre class="ui-log">{{ $robotsPreview }}</pre>
                    @endif
                </x-ui.card>
            </div>
        </form>
    </x-ui.page>
</x-app-layout>
