@props([
    'icon' => 'inbox',
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'ui-empty']) }}>
    <span class="ui-empty-icon"><x-ui.icon :name="$icon" /></span>
    @if($title)<p class="ui-empty-title">{{ $title }}</p>@endif
    <p class="ui-empty-text">{{ $slot }}</p>
    @isset($actions)
        <div class="ui-page-actions mt-1">{{ $actions }}</div>
    @endisset
</div>
