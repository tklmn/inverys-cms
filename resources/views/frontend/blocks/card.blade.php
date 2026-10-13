@php
    $url = ! empty($data['url']) ? \App\Helpers\HtmlHelper::safeUrl($data['url']) : null;
    $icon = \App\Support\ContentIcons::has($data['icon'] ?? null) ? $data['icon'] : null;
    $variant = match($settings['style'] ?? '') {
        'plain' => 'site-card-plain',
        'tinted' => 'site-card-tinted',
        default => '',
    };
    $center = ($settings['align'] ?? '') === 'center';
    $iconClass = ($settings['icon_style'] ?? '') === 'gradient' ? 'site-icon-badge site-icon-badge-gradient' : 'site-icon-badge';
@endphp
<div class="block-card site-card {{ $variant }} {{ $center ? 'site-card-center' : '' }} {{ $url ? 'is-link' : '' }} h-full {{ $settings['class'] ?? '' }}">
    @if(! empty($data['image']))
        {{-- Image: cover image (default), round portrait (team) or fully visible (logos, graphics) --}}
        @php($imageStyle = ['round' => 'site-card-media-round', 'contain' => 'site-card-media site-card-media-contain'][$settings['image_style'] ?? ''] ?? 'site-card-media')
        <img src="{{ Str::startsWith($data['image'], ['http://', 'https://']) ? $data['image'] : asset($data['image']) }}" alt="{{ $data['title'] ?? '' }}" class="{{ $imageStyle }}" loading="lazy">
    @endif
    <div class="site-card-body">
        @if($icon)
            <span class="{{ $iconClass }} mb-2"><x-ui.icon :name="$icon" /></span>
        @endif
        @if(! empty($data['title']))
            <h3 class="site-card-title"@pbField('title')>{{ $data['title'] }}</h3>
        @endif
        @if(! empty($data['content']))
            <p class="site-card-text"@pbField('content')>{{ $data['content'] }}</p>
        @endif
        @if($url)
            <a href="{{ $url }}" class="site-link-arrow">
                {{ ! empty($data['link_text']) ? $data['link_text'] : __('Read more') }} <x-ui.icon name="arrow-right" />
            </a>
        @endif
    </div>
</div>
