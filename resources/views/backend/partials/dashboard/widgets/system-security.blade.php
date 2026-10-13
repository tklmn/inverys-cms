@php
    /*
     * System & security: state of the installation (version, components) and
     * the notices from App\Support\SystemHealth. Warnings come first; the
     * notice about a missing security.txt is only a recommendation.
     */
    $security = $systemSecurity ?? ['summary' => [], 'checks' => []];
    $sbom = $security['summary'];
    $checks = collect($security['checks']);
    $warnings = $checks->where('warning', true);
@endphp

<div class="ui-card-head">
    <h3 class="ui-card-title"><span class="ui-chip ui-chip-sm ui-tint-positive"><x-ui.icon name="shield-check" /></span>{{ __('translation.dashboard.system_security') }}</h3>
    <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="route('backend.system.components')">
        {{ __('translation.dashboard.security.view_components') }}
    </x-ui.button>
</div>

<div class="ui-card-body flex flex-col gap-3">
    {{-- Two columns even in the narrow dashboard column: four short values --}}
    <x-ui.facts class="grid-cols-2!">
        <x-ui.fact label="CMS">{{ $sbom['cms_version'] ?? config('cms.version') }}</x-ui.fact>
        <x-ui.fact :label="__('translation.dashboard.security.components')">{{ $sbom['runtime_total'] ?? '—' }}</x-ui.fact>
        <x-ui.fact :label="__('translation.dashboard.security.plugins')">{{ ($sbom['plugins_enabled'] ?? 0).' / '.($sbom['counts']['plugin'] ?? 0) }}</x-ui.fact>
        <x-ui.fact label="PHP">{{ $sbom['php_version'] ?? PHP_VERSION }}</x-ui.fact>
    </x-ui.facts>

    @if($warnings->isEmpty())
        <x-ui.note tone="positive" :title="__('translation.dashboard.security.all_good')">
            {{ __('translation.dashboard.security.all_good_text') }}
        </x-ui.note>
    @endif

    @foreach($checks as $check)
        <x-ui.note :tone="$check['tone']" :icon="$check['icon']" :title="$check['title']" :data-health="$check['key']">
            {{ $check['text'] }}
            @if($check['href'])
                <div class="mt-1.5">
                    <x-ui.button variant="ghost" size="xs" trailing-icon="arrow-right" :href="$check['href']">
                        {{ __('translation.dashboard.health.fix') }}
                    </x-ui.button>
                </div>
            @endif
        </x-ui.note>
    @endforeach

    <div>
        <x-ui.button variant="secondary" size="sm" icon="download" :href="route('backend.system.sbom.download')">
            {{ __('translation.dashboard.security.download') }}
        </x-ui.button>
    </div>
</div>
