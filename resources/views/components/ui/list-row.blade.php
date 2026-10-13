@props([
    'title' => null,
    'icon' => null,
    'href' => null,
    'selected' => false,
])

@php
    // A row is either a link or — if a detail was passed —
    // the trigger for the detail panel.
    $clickable = $href !== null || isset($detail);
@endphp

<div
    {{ $attributes->merge(['class' => 'ui-list-row']) }}
    role="listitem"
    @if($clickable) data-clickable @endif
    @if($href) data-href="{{ $href }}" @endif
    @if($selected) aria-selected="true" @endif
>
    @if($icon)
        <span class="ui-list-lead"><x-ui.nav-icon :name="$icon" /></span>
    @elseif(isset($lead))
        <span class="ui-list-lead">{{ $lead }}</span>
    @endif

    <div class="ui-list-main">
        @if($href)
            <a href="{{ $href }}" class="ui-list-title">{{ $title }}</a>
        @else
            <span class="ui-list-title">{{ $title }}</span>
        @endif

        @isset($meta)
            <span class="ui-list-meta">{{ $meta }}</span>
        @endisset
    </div>

    @isset($cols)
        <div class="ui-list-cols">{{ $cols }}</div>
    @endisset

    @isset($actions)
        <div class="ui-list-actions">{{ $actions }}</div>
    @endisset

    @isset($detail)
        {{-- Content of the detail panel; only moved into the DOM when opened --}}
        <template data-detail data-title="{{ $title }}">{{ $detail }}</template>
    @endisset
</div>
