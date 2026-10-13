@php
    $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['value'] ?? null) || filled($item['label'] ?? null)));
    $cards = ($settings['style'] ?? 'cards') === 'cards';
    $align = ($settings['align'] ?? 'center') === 'center' ? 'site-text-center' : '';
@endphp
@if($items)
<div class="block-stats site-stats {{ $cards ? 'site-stats-cards' : '' }} {{ $align }} {{ $settings['class'] ?? '' }}">
    @foreach($items as $item)
        <div class="site-stat">
            <span class="site-stat-value">{{ $item['value'] ?? '' }}</span>
            <span class="site-stat-label">{{ $item['label'] ?? '' }}</span>
        </div>
    @endforeach
</div>
@endif
