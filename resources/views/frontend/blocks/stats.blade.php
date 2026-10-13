@php
    $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['value'] ?? null) || filled($item['label'] ?? null)));
    $cards = ($settings['style'] ?? 'cards') === 'cards';
    $align = ($settings['align'] ?? 'center') === 'center' ? 'site-text-center' : '';
    // Count up when scrolled into view (resources/js/frontend/count-up.js). The HTML
    // always holds the final value: without script, for search engines and for
    // screen readers, which read the hidden copy instead of the changing digits.
    $animate = ! empty($settings['animate']);
@endphp
@if($items)
<div class="block-stats site-stats {{ $cards ? 'site-stats-cards' : '' }} {{ $align }} {{ $settings['class'] ?? '' }}" role="list" @if($animate) data-count-up @endif>
    @foreach($items as $item)
        <div class="site-stat" role="listitem">
            @if($animate && filled($item['value'] ?? null))
                <span class="site-stat-value"><span class="sr-only">{{ $item['value'] }}</span><span aria-hidden="true" data-count-up-value>{{ $item['value'] }}</span></span>
            @else
                <span class="site-stat-value">{{ $item['value'] ?? '' }}</span>
            @endif
            <span class="site-stat-label">{{ $item['label'] ?? '' }}</span>
        </div>
    @endforeach
</div>
@endif
