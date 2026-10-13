@php $w = $widgetWidth ?? 100; @endphp

<button type="button" data-set-width="25" class="dw-w-btn {{ $w == 25 ? 'active' : '' }}" title="25%">&#188;</button>
<button type="button" data-set-width="33" class="dw-w-btn {{ $w == 33 ? 'active' : '' }}" title="33%">&#8531;</button>
<button type="button" data-set-width="50" class="dw-w-btn {{ $w == 50 ? 'active' : '' }}" title="50%">&#189;</button>
<button type="button" data-set-width="66" class="dw-w-btn {{ $w == 66 ? 'active' : '' }}" title="66%">&#8532;</button>
<button type="button" data-set-width="100" class="dw-w-btn {{ $w == 100 ? 'active' : '' }}" title="100%">1</button>

<span class="mx-0.5 h-4 w-px bg-line"></span>

<button
    type="button"
    data-remove="{{ $widgetId }}"
    class="ui-btn ui-btn-xs ui-btn-icon ui-btn-danger"
    title="{{ __('translation.dashboard.remove_widget') }}"
    aria-label="{{ __('translation.dashboard.remove_widget') }}"
>
    <x-ui.icon name="x" />
</button>
