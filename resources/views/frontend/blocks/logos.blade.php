@php
    // Logo strip (customers, partners); muted = grey, coloured on hover
    $items = array_values(array_filter($data['items'] ?? [], fn ($item) => filled($item['image'] ?? null)));
    $muted = ($settings['style'] ?? 'muted') === 'muted';
@endphp
@if($items)
<div class="block-logos site-logos {{ $muted ? 'site-logos-muted' : '' }} {{ $settings['class'] ?? '' }}">
    @foreach($items as $item)
        @php($src = Str::startsWith($item['image'], ['http://', 'https://']) ? $item['image'] : asset($item['image']))
        @if(! empty($item['url']))
            <a href="{{ \App\Helpers\HtmlHelper::safeUrl($item['url']) }}" target="_blank" rel="noopener noreferrer">
                <img src="{{ $src }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy">
            </a>
        @else
            <img src="{{ $src }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy">
        @endif
    @endforeach
</div>
@endif
