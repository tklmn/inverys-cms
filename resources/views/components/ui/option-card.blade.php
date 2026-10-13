@props([
    'name',
    'value',
    'title',
    'description' => null,
    'icon' => null,
    'tone' => 'accent',
    'checked' => false,
    'disabled' => false,
    'disabledReason' => null,
    'badge' => null,
    'form' => null,
])

{{--
    Choice as a card: one of several options, each with an icon, a
    title and one sentence of explanation. For decisions where the
    description helps with choosing (mail transport, content mode …) — for
    short lists x-ui.select is enough.

    `tone`     — colour of the icon chip (accent|positive|caution|critical|violet|teal)
    `badge`    — short addition at the top right, e.g. "Active"
    `disabled` — greyed out instead of hidden; the reason is shown in the card
    `form`     — id of the form if the card sits outside of it
--}}
<label {{ $attributes->merge(['class' => 'ui-option'.($disabled ? ' is-disabled' : '')]) }} @if($disabled && $disabledReason) title="{{ $disabledReason }}" @endif>
    <input type="radio" name="{{ $name }}" value="{{ $value }}" class="sr-only" @if($form) form="{{ $form }}" @endif @checked($checked) @disabled($disabled)>

    @if($icon)
        <span class="ui-option-icon ui-tint-{{ $tone }}"><x-ui.icon :name="$icon" /></span>
    @endif

    <span class="ui-option-body">
        <span class="ui-option-title">
            {{ $title }}
            @if($badge)<x-ui.badge tone="positive" class="ms-auto">{{ $badge }}</x-ui.badge>@endif
        </span>

        @if($description)<span class="ui-option-text">{{ $description }}</span>@endif

        @if($disabled && $disabledReason)
            <span class="ui-option-reason"><x-ui.icon name="lock" /> {{ $disabledReason }}</span>
        @endif
    </span>

    {{-- Selection dot: shows at a glance which card is selected --}}
    <span class="ui-option-mark" aria-hidden="true"></span>
</label>
