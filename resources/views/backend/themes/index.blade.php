<x-app-layout>
    <x-slot name="header">{{ __('Themes') }}</x-slot>

    <x-ui.page :title="__('Themes')" :lead="__('translation.settings.where.themes_lead')">
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
            <x-ui.button size="md" icon="upload" data-csp="theme-upload-toggle" :disabled="$codeBlocked !== null" :disabled-reason="$codeBlocked">
                {{ __('translation.upload_package') }}
            </x-ui.button>
        </x-slot:actions>

        {{-- Upload area, collapsed by default --}}
        <div id="theme-upload-area" class="hidden">
            <x-ui.card>
                <form action="{{ route('backend.themes.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <x-ui.field name="package" class="flex-1">
                        <x-ui.dropzone
                            name="package"
                            :label="__('translation.upload_theme_zip')"
                            :hint="__('translation.zip_max_20mb')"
                            required
                        />
                    </x-ui.field>
                    <x-ui.button variant="primary" size="md" icon="upload" type="submit">{{ __('translation.install') }}</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        {{-- Fixed tile width: theme cards should be the same size, even with only two themes --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($themes as $theme)
                @php
                    $screenshotPath = ! empty($theme['screenshot']) ? $theme['path'].'/'.$theme['screenshot'] : null;
                    $hasScreenshot = $screenshotPath && file_exists($screenshotPath);
                    $isActive = $activeTheme === $theme['slug'];
                    $pkg = ['slug' => $theme['slug'], 'name' => __($theme['name']), 'backup' => $theme['backup'] ?? null];
                @endphp

                <div class="ui-card overflow-hidden {{ $isActive ? 'border-accent' : '' }}">
                    <div class="flex h-36 items-center justify-center bg-surface-sunken">
                        @if($hasScreenshot)
                            <img
                                src="data:{{ str_ends_with($screenshotPath, '.svg') ? 'image/svg+xml' : 'image/png' }};base64,{{ base64_encode(file_get_contents($screenshotPath)) }}"
                                alt="{{ __($theme['name']) }}"
                                class="h-full w-full object-cover object-top"
                            >
                        @else
                            <x-ui.icon name="palette" class="text-3xl text-fg-subtle" />
                        @endif
                    </div>

                    <div class="ui-card-body">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <h3 class="ui-card-title">{{ __($theme['name']) }}</h3>
                            <span class="flex items-center gap-2">
@if($update = $updates[$theme['slug']] ?? null)
                                @if(! empty($update['release']['url']))
                                    <a href="{{ $update['release']['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ __('translation.updates.release_page') }}"><x-ui.badge tone="accent">{{ __('translation.updates.status_available', ['version' => $update['latest']]) }}</x-ui.badge></a>
                                @else
                                    <x-ui.badge tone="accent">{{ __('translation.updates.status_available', ['version' => $update['latest']]) }}</x-ui.badge>
                                @endif
                            @endif
                                <span class="ui-help">v{{ $theme['version'] ?? '1.0.0' }}</span>
                            </span>
                        </div>

                        <p class="ui-help">{{ isset($theme['description']) ? __($theme['description']) : '' }}</p>
                        <p class="mt-1 text-xs text-fg-muted">{{ __('By') }} {{ $theme['author'] ?? __('Unknown') }}</p>

                        {{-- Main actions on the left, icon buttons on the right — disabled instead of hidden --}}
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if($isActive)
                                <x-ui.badge tone="positive">{{ __('Active') }}</x-ui.badge>
                            @else
                                <form action="{{ route('backend.themes.activate') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="slug" value="{{ $theme['slug'] }}">
                                    <x-ui.button variant="secondary" icon="check" type="submit">{{ __('Activate') }}</x-ui.button>
                                </form>
                            @endif

                            <x-ui.button
                                icon="sliders-horizontal"
                                :href="route('backend.themes.customize', $theme['slug'])"
                                :disabled="empty($theme['settings']) && empty($theme['regions'])"
                                :disabled-reason="__('This theme has no settings.')"
                            >{{ __('Customize') }}</x-ui.button>

                            @php
                                $update = $updates[$pkg['slug']] ?? null;
                                $updateReason = $codeBlocked ?? ($update && $update['manager'] !== 'zip' ? \App\Services\Updates\PackageUpdater::reason($update['manager']) : null);
                            @endphp
                            {{-- Only for a newer version; if not possible here (Git, Composer, locked), disabled with a reason --}}
                            @if($update)
                                @if($updateReason === null)
                                    <form action="{{ route('backend.themes.update', $pkg['slug']) }}" method="POST"
                                          data-confirm="{{ __('translation.updates.confirm_update', ['name' => $pkg['name'], 'version' => $update['latest']]) }}">
                                        @csrf
                                        <x-ui.button variant="primary" icon="download" type="submit">{{ __('translation.updates.update_to', ['version' => $update['latest']]) }}</x-ui.button>
                                    </form>
                                @else
                                    <x-ui.button variant="primary" icon="download" :disabled="true" :disabled-reason="$updateReason">{{ __('translation.updates.update_to', ['version' => $update['latest']]) }}</x-ui.button>
                                @endif
                            @endif
                            {{-- Way back after an update: the last backed-up state --}}
                            @if($pkg['backup'])
                                @if($codeBlocked === null)
                                    <form action="{{ route('backend.themes.restore', $pkg['slug']) }}" method="POST"
                                          data-confirm="{{ __('translation.updates.confirm_restore', ['name' => $pkg['name'], 'version' => $pkg['backup']['version']]) }}">
                                        @csrf
                                        <x-ui.button variant="ghost" icon="rotate-ccw" type="submit" :label="__('translation.updates.restore_version', ['version' => $pkg['backup']['version']])" />
                                    </form>
                                @else
                                    <x-ui.button variant="ghost" icon="rotate-ccw" :label="__('translation.updates.restore_version', ['version' => $pkg['backup']['version']])" :disabled="true" :disabled-reason="$codeBlocked" />
                                @endif
                            @endif

                            <span class="ms-auto flex items-center gap-0.5">
                                @permission('edit-theme-files')
                                    <x-ui.button
                                        variant="ghost"
                                        icon="file-code"
                                        :label="__('translation.theme_editor.edit_files')"
                                        :href="route('backend.themes.editor', $theme['slug'])"
                                        :disabled="empty($theme['editable']) || $codeBlocked !== null"
                                        :disabled-reason="$codeBlocked ?? __('The files of this theme cannot be edited.')"
                                    />
                                @endpermission

                                @if($theme['slug'] === 'default' || $isActive || $codeBlocked !== null)
                                    <x-ui.button
                                        variant="danger"
                                        icon="trash-2"
                                        :label="__('Delete')"
                                        :disabled="true"
                                        :disabled-reason="$codeBlocked ?? ($theme['slug'] === 'default' ? __('The default theme cannot be deleted.') : __('The active theme cannot be deleted.'))"
                                    />
                                @else
                                    <form id="delete-form-theme-{{ $theme['slug'] }}" action="{{ route('backend.themes.destroy', $theme['slug']) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button
                                            variant="danger"
                                            icon="trash-2"
                                            :label="__('Delete')"
                                            data-delete-modal="{{ json_encode(['formId' => 'delete-form-theme-'.$theme['slug']]) }}"
                                        />
                                    </form>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-ui.card>
                        <x-ui.empty icon="palette">{{ __('No themes found.') }}</x-ui.empty>
                    </x-ui.card>
                </div>
            @endforelse
        </div>
    </x-ui.page>

    <script @cspNonce>
        document.querySelectorAll('[data-csp="theme-upload-toggle"]').forEach(function (el) {
            el.addEventListener('click', function () {
                document.getElementById('theme-upload-area').classList.toggle('hidden');
            });
        });
    </script>
</x-app-layout>
