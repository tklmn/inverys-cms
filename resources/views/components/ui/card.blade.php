@props([
    'title' => null,
    'flush' => false,
    'icon' => null,
    'tone' => 'accent',
])

<div {{ $attributes->merge(['class' => 'ui-card']) }}>
    @if($title || isset($head))
        <div class="ui-card-head">
            @if($title)
                <h2 class="ui-card-title">
                    @if($icon)<span class="ui-chip ui-chip-sm ui-tint-{{ $tone }}"><x-ui.icon :name="$icon" /></span>@endif
                    {{ $title }}
                </h2>
            @endif
            @isset($head)<div class="ui-page-actions">{{ $head }}</div>@endisset
        </div>
    @endif

    <div class="{{ $flush ? 'ui-card-body-flush' : 'ui-card-body' }}">{{ $slot }}</div>

    @isset($foot)
        <div class="ui-card-foot">{{ $foot }}</div>
    @endisset
</div>
