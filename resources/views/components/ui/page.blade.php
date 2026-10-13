@props([
    'title' => null,
    'lead' => null,
])

<div {{ $attributes->merge(['class' => 'ui-page']) }}>
    @if($title || isset($actions))
        <div class="ui-page-head">
            <div class="min-w-0">
                @if($title)<h1 class="ui-page-title">{{ $title }}</h1>@endif
                @if($lead)<p class="ui-page-lead">{{ $lead }}</p>@endif
            </div>
            @isset($actions)
                <div class="ui-page-actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <x-ui.flash />

    {{ $slot }}
</div>
