<x-app-layout>
    <x-slot name="header">{{ __('translation.logs.show') }}: {{ $filename }}</x-slot>

    <x-ui.page :title="$filename" :lead="$size.' · '.__('translation.logs.modified').': '.format_datetime($modified)">
        <x-slot:actions>
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.logs.index')">{{ __('translation.logs.title') }}</x-ui.button>
            <x-ui.button size="md" icon="copy" data-copy="#log-content">{{ __('translation.logs.copy') }}</x-ui.button>

            @permission('download-logs')
                <x-ui.button size="md" icon="download" :href="route('backend.logs.download', $filename)">
                    {{ __('translation.logs.download') }}
                </x-ui.button>
            @endpermission

            @permission('clear-logs')
                <form id="delete-form-log-show" action="{{ route('backend.logs.clear', $filename) }}" method="POST">
                    @csrf
                    <x-ui.button
                        variant="danger"
                        size="md"
                        icon="eraser"
                        data-delete-modal="{{ json_encode(['formId' => 'delete-form-log-show', 'title' => __('translation.logs.confirm_clear'), 'subtitle' => $filename]) }}"
                    >{{ __('translation.logs.clear') }}</x-ui.button>
                </form>
            @endpermission
        </x-slot:actions>

        @if($truncated)
            <x-ui.note tone="caution">{{ __('translation.logs.truncated') }}</x-ui.note>
        @endif

        {{--
            Entry header lines ("[2026-09-25 …] local.ERROR: …") coloured by
            level: otherwise the start of an error is lost in a thousand lines
            of stack trace. The text stays unchanged — "Copy" still returns
            the raw log. PHP_EOL writes the line break: Blade
            swallows the line break directly after a directive.
        --}}
        @php
            $logLines = preg_split('/\R/', rtrim($content));
            $logLevel = function (string $line): ?string {
                return preg_match('/^\[[^\]]+\]\s+[\w-]+\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG):/', $line, $hit)
                    ? strtolower($hit[1])
                    : null;
            };
        @endphp

        <x-ui.card flush>
<pre id="log-content" class="ui-log">@foreach($logLines as $line)@php($level = $logLevel($line))@if($level)<span class="ui-log-head ui-log-{{ $level }}">{{ $line }}</span>@else{{ $line }}@endif{{ PHP_EOL }}@endforeach</pre>
        </x-ui.card>
    </x-ui.page>

</x-app-layout>
