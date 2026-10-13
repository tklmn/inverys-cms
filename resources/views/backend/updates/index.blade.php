<x-app-layout>
    <x-slot name="header">{{ __('translation.updates.title') }}</x-slot>

    @php
        // Release notes come as Markdown from GitHub: HTML in them is
        // escaped, unsafe links removed
        $notes = fn (?array $release) => $release && trim((string) $release['notes']) !== ''
            ? \Illuminate\Support\Str::markdown((string) $release['notes'], ['html_input' => 'escape', 'allow_unsafe_links' => false])
            : null;
        // Updating from the release: only with a complete package attached to the release
        $updateReason = $blocked ?? ($package === null ? __('translation.updates.reason_core_no_package') : null);
        $isGit = $blocked === __('translation.updates.reason_core_git');
    @endphp

    <x-ui.page :title="__('translation.updates.title')">
        {{-- The same buttons as for plugins and themes --}}
        <x-slot:actions>
            <form action="{{ route('backend.updates.check') }}" method="POST">
                @csrf
                <x-ui.button size="md" icon="refresh-cw" type="submit">{{ __('translation.updates.check_packages') }}</x-ui.button>
            </form>
            <x-ui.button size="md" icon="upload" data-csp="core-upload-toggle" :disabled="$blocked !== null" :disabled-reason="$blocked">
                {{ __('translation.upload_package') }}
            </x-ui.button>
        </x-slot:actions>

        {{-- Upload area, collapsed as for plugins and themes --}}
        @if($blocked === null)
            <div id="core-upload-area" class="hidden">
                <x-ui.card>
                    <form action="{{ route('backend.updates.core.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3"
                          data-confirm="{{ __('translation.updates.confirm_core_upload') }}">
                        @csrf
                        <x-ui.field name="package" class="flex-1">
                            <x-ui.dropzone
                                name="package"
                                :label="__('translation.updates.upload_label')"
                                :hint="__('translation.updates.upload_hint', ['size' => number_format($maxUpload / 1024, 0, ',', '.')])"
                                required
                            />
                        </x-ui.field>
                        <x-ui.button variant="primary" size="md" icon="upload" type="submit">{{ __('translation.install') }}</x-ui.button>
                    </form>
                </x-ui.card>
            </div>
        @endif

        {{-- An update stalled after step 1 (page closed …) — finish it here --}}
        @if($pending)
            <x-ui.note tone="caution" :title="__('translation.updates.pending_title', ['version' => $pending['to']])">
                {{ __('translation.updates.pending_text') }}
                <a class="underline" href="{{ route('backend.updates.core.finish', ['token' => $pending['token']]) }}">{{ __('translation.updates.finishing_continue') }}</a>
            </x-ui.note>
        @endif

        <x-ui.card :title="$core['name']" icon="package">
            <x-ui.facts>
                <x-ui.fact :label="__('translation.updates.installed')">{{ $core['installed'] }}</x-ui.fact>
                <x-ui.fact :label="__('translation.updates.latest')">{{ $core['latest'] ?? '—' }}</x-ui.fact>
                <x-ui.fact :label="__('translation.updates.status')">
                    @if($core['error'])
                        <x-ui.badge tone="critical">{{ __('translation.updates.status_error') }}</x-ui.badge>
                    @elseif($core['available'])
                        <x-ui.badge tone="accent">{{ __('translation.updates.status_available', ['version' => $core['latest']]) }}</x-ui.badge>
                    @elseif($core['latest'])
                        <x-ui.badge tone="positive">{{ __('translation.updates.status_current') }}</x-ui.badge>
                    @else
                        <x-ui.badge>{{ __('translation.updates.status_unknown') }}</x-ui.badge>
                    @endif
                </x-ui.fact>
                <x-ui.fact :label="__('translation.updates.last_check')">{{ $checkedAt ? format_datetime($checkedAt, true) : __('translation.updates.not_checked') }}</x-ui.fact>
            </x-ui.facts>

            @if($core['error'])
                <x-ui.note tone="critical" class="mt-4">{{ $core['error'] }}</x-ui.note>
            @endif

            @if($core['available'] && ($html = $notes($core['release'])))
                <div class="ui-release-notes mt-4">{!! $html !!}</div>
            @endif

            {{-- Git installation: via git pull, Composer and build --}}
            @if($isGit)
                <x-ui.note tone="neutral" class="mt-4" :title="$blocked">
                    @php
                        // Commands, not text — the same in every language
                        $steps = ['git pull', 'composer install --no-dev --optimize-autoloader', 'php artisan migrate --force', 'npm ci && npm run build', 'php artisan optimize:clear'];
                    @endphp
                    <ol class="ui-release-steps">
                        @foreach($steps as $step)
                            <li><code>{{ $step }}</code></li>
                        @endforeach
                    </ol>
                </x-ui.note>
            @elseif($blocked !== null)
                <x-ui.note tone="caution" class="mt-4">{{ $blocked }}</x-ui.note>
            @endif

            @if(($core['available'] && ! $isGit) || $backup || ($core['release']['url'] ?? null))
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    {{-- Only for a newer version; if not possible here, disabled with a reason.
                         Git installation: the steps above replace the button --}}
                    @if($core['available'] && ! $isGit)
                        @if($updateReason === null)
                            <form action="{{ route('backend.updates.core.update') }}" method="POST"
                                  data-confirm="{{ __('translation.updates.confirm_core_update', ['version' => $core['latest']]) }}">
                                @csrf
                                <x-ui.button variant="primary" icon="download" type="submit">{{ __('translation.updates.update_to', ['version' => $core['latest']]) }}</x-ui.button>
                            </form>
                        @else
                            <x-ui.button variant="primary" icon="download" :disabled="true" :disabled-reason="$updateReason">{{ __('translation.updates.update_to', ['version' => $core['latest']]) }}</x-ui.button>
                        @endif
                    @endif

                    {{-- Way back: roll back the last update --}}
                    @if($backup)
                        @if($blocked === null)
                            <form action="{{ route('backend.updates.core.restore') }}" method="POST"
                                  data-confirm="{{ __('translation.updates.confirm_core_restore', ['version' => $backup['from']]) }}">
                                @csrf
                                <x-ui.button icon="rotate-ccw" type="submit">{{ __('translation.updates.restore_version', ['version' => $backup['from']]) }}</x-ui.button>
                            </form>
                        @else
                            <x-ui.button icon="rotate-ccw" :disabled="true" :disabled-reason="$blocked">{{ __('translation.updates.restore_version', ['version' => $backup['from']]) }}</x-ui.button>
                        @endif
                    @endif

                    @if($core['release']['url'] ?? null)
                        <x-ui.button variant="ghost" icon="external-link" class="ms-auto" :href="$core['release']['url']" target="_blank" rel="noopener noreferrer">
                            {{ __('translation.updates.release_page') }}
                        </x-ui.button>
                    @endif
                </div>
            @endif
        </x-ui.card>
    </x-ui.page>

    <script @cspNonce>
        document.querySelectorAll('[data-csp="core-upload-toggle"]').forEach(function (el) {
            el.addEventListener('click', function () {
                var area = document.getElementById('core-upload-area');
                if (area) area.classList.toggle('hidden');
            });
        });
    </script>
</x-app-layout>
