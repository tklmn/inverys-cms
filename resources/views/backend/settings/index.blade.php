<x-app-layout>
    <x-slot name="header">{{ __('translation.settings.title') }}</x-slot>

    <x-ui.page :title="__('translation.settings.title')" :lead="__('translation.settings.lead')">
        <x-ui.card flush>
            <x-ui.toolbar>
                <x-ui.input
                    icon="search"
                    type="search"
                    class="w-full sm:w-72"
                    data-filter-input
                    :placeholder="__('translation.search')"
                    :aria-label="__('translation.filter')"
                />
            </x-ui.toolbar>

            <x-ui.list data-filter-scope>
                @forelse($settings as $setting)
                    @php
                        $definition = \App\Support\GeneralSettings::definition($setting->key);
                        $managed = $definition['managed'];
                        // Settings with their own page: locked with a reference instead of hidden
                        $mayOpen = $managed && (! $managed['permission'] || auth()->user()->hasPermission($managed['permission']));
                        $href = $managed ? ($mayOpen ? route($managed['route']) : null) : route('backend.settings.edit', $setting);
                        $display = \App\Support\GeneralSettings::display($setting->key, $setting->value);
                    @endphp
                    <x-ui.list-row
                        :title="$definition['label']"
                        :icon="$definition['icon']"
                        :href="$href"
                        data-filter-item="{{ Str::lower($setting->key.' '.$definition['label'].' '.$setting->value.' '.$display) }}"
                    >
                        <x-slot:meta>
                            <span class="font-mono">{{ $setting->key }}</span>
                            @if($managed)
                                <span class="ui-meta-flag"><x-ui.icon name="lock" />{{ __('translation.settings.managed_by', ['page' => $managed['label']]) }}</span>
                            @endif
                            {{-- Only a hint, not a lock — hence a plain meta note instead of a highlight --}}
                            @if($definition['user_override'])
                                <span>{{ __('translation.settings.user_override') }}</span>
                            @endif
                        </x-slot:meta>

                        <x-slot:cols>
                            <x-ui.list-col :label="__('translation.settings.value')">
                                @if(blank($setting->value))
                                    <span class="text-fg-muted">{{ __('translation.settings.value_empty') }}</span>
                                @else
                                    <span class="block max-w-60 truncate" title="{{ $setting->value }}">{{ $display }}</span>
                                @endif
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            @if(! $managed)
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="$href" />
                            @elseif($mayOpen)
                                <x-ui.button variant="ghost" icon="arrow-right" :label="__('translation.settings.managed_open', ['page' => $managed['label']])" :href="$href" />
                            @else
                                <x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :disabled="true" :disabled-reason="__('translation.settings.managed_reason', ['page' => $managed['label']])" />
                            @endif
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="settings">{{ __('translation.no_results') }}</x-ui.empty>
                @endforelse

                <div class="ui-empty hidden" data-filter-empty>
                    <span class="ui-empty-icon"><x-ui.icon name="search" /></span>
                    <p class="ui-empty-text">{{ __('translation.no_results') }}</p>
                </div>
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>
</x-app-layout>
