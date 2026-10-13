<x-app-layout>
    <x-slot name="header">{{ __('Plugins') }}</x-slot>

    <x-ui.page :title="__('Plugins')">
        @php
            $codeBlocked = \App\Support\CodeAccess::blockedReason(auth()->user());
        @endphp
        <x-slot:actions>
            @permission('manage-updates')
                <form action="{{ route('backend.updates.check') }}" method="POST">
                    @csrf
                    <x-ui.button size="md" icon="refresh-cw" type="submit" :title="$checkedAt ? __('translation.updates.checked_at', ['time' => format_datetime($checkedAt, true)]) : __('translation.updates.never_checked')">{{ __('translation.updates.check_packages') }}</x-ui.button>
                </form>
            @endpermission
            <x-ui.button size="md" icon="upload" data-csp="plugin-upload-toggle" :disabled="$codeBlocked !== null" :disabled-reason="$codeBlocked">
                {{ __('translation.upload_package') }}
            </x-ui.button>
        </x-slot:actions>

        <div id="plugin-upload-area" class="hidden">
            <x-ui.card>
                <form action="{{ route('backend.plugins.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <x-ui.field name="package" class="flex-1">
                        <x-ui.dropzone
                            name="package"
                            :label="__('translation.upload_plugin_zip')"
                            :hint="__('translation.zip_max_20mb')"
                            required
                        />
                    </x-ui.field>
                    <x-ui.button variant="primary" size="md" icon="upload" type="submit">{{ __('translation.install') }}</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <x-ui.card flush>
            @if($hasLanguagePacks)
                {{-- All plugins or only the language packs --}}
                <x-ui.toolbar>
                    <nav class="flex flex-wrap gap-2" aria-label="{{ __('translation.filter') }}">
                        <x-ui.button size="sm" :variant="$type === null ? 'secondary' : 'ghost'" :href="route('backend.plugins.index')" :aria-current="$type === null ? 'page' : null">{{ __('translation.plugins_filter_all') }}</x-ui.button>
                        <x-ui.button size="sm" icon="languages" :variant="$type === 'language' ? 'secondary' : 'ghost'" :href="route('backend.plugins.index', ['type' => 'language'])" :aria-current="$type === 'language' ? 'page' : null">{{ __('translation.plugins_filter_language') }}</x-ui.button>
                    </nav>
                </x-ui.toolbar>
            @endif
            <x-ui.list>
                @forelse($plugins as $plugin)
                    <x-ui.list-row :title="$plugin['name']" icon="puzzle">
                        <x-slot:meta>
                            @if($plugin['description'])<span>{{ $plugin['description'] }}</span>@endif
                            <span>v{{ $plugin['version'] }}</span>
@if($update = $updates[$plugin['slug']] ?? null)
                                @if(! empty($update['release']['url']))
                                    <a href="{{ $update['release']['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ __('translation.updates.release_page') }}"><x-ui.badge tone="accent">{{ __('translation.updates.status_available', ['version' => $update['latest']]) }}</x-ui.badge></a>
                                @else
                                    <x-ui.badge tone="accent">{{ __('translation.updates.status_available', ['version' => $update['latest']]) }}</x-ui.badge>
                                @endif
                            @endif
                            <span>{{ __('By') }} {{ $plugin['author'] }}</span>
                        </x-slot:meta>

                        <x-slot:cols>
                            {{-- On/off as a switch instead of a badge + "Disable" button:
                                 state and control are the same element, it is saved
                                 immediately (data-autosubmit, shared/csp-actions.js) --}}
                            <x-ui.list-col>
                                <form action="{{ route($plugin['is_enabled'] ? 'backend.plugins.disable' : 'backend.plugins.enable', $plugin['slug']) }}" method="POST">
                                    @csrf
                                    <x-ui.toggle
                                        name="enabled"
                                        :checked="$plugin['is_enabled']"
                                        :label="$plugin['is_enabled'] ? __('Enabled') : __('Disabled')"
                                        :aria-label="($plugin['is_enabled'] ? __('Disable') : __('Enable')).': '.$plugin['name']"
                                        data-autosubmit
                                    />
                                </form>
                            </x-ui.list-col>
                        </x-slot:cols>

                        <x-slot:actions>
                            {{--
                                Every row shows the same two actions in the same
                                order. What is not possible right now is disabled instead of
                                left out — otherwise the columns shift.
                            --}}
                            @php
                                $update = $updates[$plugin['slug']] ?? null;
                                $updateReason = $codeBlocked ?? ($update && $update['manager'] !== 'zip' ? \App\Services\Updates\PackageUpdater::reason($update['manager']) : null);
                            @endphp
                            {{-- Only for a newer version; if it cannot be done here (Git, Composer, locked), disabled with a reason --}}
                            @if($update)
                                @if($updateReason === null)
                                    <form action="{{ route('backend.plugins.update', $plugin['slug']) }}" method="POST"
                                          data-confirm="{{ __('translation.updates.confirm_update', ['name' => $plugin['name'], 'version' => $update['latest']]) }}">
                                        @csrf
                                        <x-ui.button variant="primary" icon="download" type="submit">{{ __('translation.updates.update_to', ['version' => $update['latest']]) }}</x-ui.button>
                                    </form>
                                @else
                                    <x-ui.button variant="primary" icon="download" :disabled="true" :disabled-reason="$updateReason">{{ __('translation.updates.update_to', ['version' => $update['latest']]) }}</x-ui.button>
                                @endif
                            @endif
                            {{-- Way back after an update: the last backed-up state --}}
                            @if($plugin['backup'])
                                @if($codeBlocked === null)
                                    <form action="{{ route('backend.plugins.restore', $plugin['slug']) }}" method="POST"
                                          data-confirm="{{ __('translation.updates.confirm_restore', ['name' => $plugin['name'], 'version' => $plugin['backup']['version']]) }}">
                                        @csrf
                                        <x-ui.button variant="ghost" icon="rotate-ccw" type="submit" :label="__('translation.updates.restore_version', ['version' => $plugin['backup']['version']])" />
                                    </form>
                                @else
                                    <x-ui.button variant="ghost" icon="rotate-ccw" :label="__('translation.updates.restore_version', ['version' => $plugin['backup']['version']])" :disabled="true" :disabled-reason="$codeBlocked" />
                                @endif
                            @endif

                            @php
                                $hasSettings = $plugin['settings_route'] && Route::has($plugin['settings_route']);
                            @endphp

                            <x-ui.button
                                variant="ghost"
                                icon="settings"
                                :label="__('Settings')"
                                :href="$hasSettings && $plugin['is_enabled'] ? route($plugin['settings_route']) : null"
                                :disabled="! $hasSettings || ! $plugin['is_enabled']"
                                :disabled-reason="$hasSettings ? __('translation.plugin_enable_first') : __('translation.plugin_no_settings')"
                            />

                            @if(($plugin['is_system'] ?? false) || $codeBlocked !== null)
                                <x-ui.button
                                    variant="danger"
                                    icon="trash-2"
                                    :label="__('Delete')"
                                    :disabled="true"
                                    :disabled-reason="($plugin['is_system'] ?? false) ? __('translation.plugin_protected') : $codeBlocked"
                                />
                            @else
                                <form id="delete-form-plugin-{{ $plugin['slug'] }}" action="{{ route('backend.plugins.destroy', $plugin['slug']) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('Delete')"
                                        :disabled="$plugin['is_enabled']"
                                        :disabled-reason="__('translation.plugin_disable_first')"
                                        data-delete-modal="{{ json_encode(['formId' => 'delete-form-plugin-'.$plugin['slug']]) }}"
                                    />
                                </form>
                            @endif
                        </x-slot:actions>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty icon="puzzle" :title="__('No plugins found.')">
                        {{ __('Place plugins in the plugins/ directory.') }}
                    </x-ui.empty>
                @endforelse
            </x-ui.list>
        </x-ui.card>
    </x-ui.page>

    <script @cspNonce>
        document.querySelectorAll('[data-csp="plugin-upload-toggle"]').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('plugin-upload-area').classList.toggle('hidden');
            });
        });
    </script>
</x-app-layout>
