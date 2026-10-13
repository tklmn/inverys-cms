@props(['icon' => null])

@if($icon)
    <span class="ui-input-group">
        <span class="ui-input-icon"><x-ui.icon :name="$icon" /></span>
        <input {{ $attributes->merge(['class' => 'ui-input', 'type' => 'text']) }}>
    </span>
@else
    <input {{ $attributes->merge(['class' => 'ui-input', 'type' => 'text']) }}>
@endif
