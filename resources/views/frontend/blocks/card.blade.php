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
    $titleTag = \App\Support\BlockDesign::headingLevel($settings['title_level'] ?? null, 'h3');
    // Older cards without their own alt text keep the title as the alternative text
    $imageAlt = \App\Support\BlockDesign::alt($data['image_alt'] ?? '', $settings['image_decorative'] ?? false, (string) ($data['title'] ?? ''));
    $newTab = \App\Support\BlockDesign::newTab($settings['link_target'] ?? null);
@endphp
<div class="block-card site-card {{ $variant }} {{ $center ? 'site-card-center' : '' }} {{ $url ? 'is-link' : '' }} h-full {{ $settings['class'] ?? '' }}">
    @if(! empty($data['image']))
        {{-- Image: cover image (default), round portrait (team) or fully visible (logos, graphics) --}}
        @php($imageStyle = ['round' => 'site-card-media-round', 'contain' => 'site-card-media site-card-media-contain'][$settings['image_style'] ?? ''] ?? 'site-card-media')
        <x-responsive-image :src="$data['image']" :sizes="($settings['image_style'] ?? '') === 'round' ? '7rem' : '(min-width: 64rem) 26rem, (min-width: 40rem) 50vw, 100vw'" :priority="$imagePriority ?? false" :focal="($settings['image_style'] ?? '') !== 'contain'" alt="{{ $imageAlt }}" class="{{ $imageStyle }}" />
    @endif
    <div class="site-card-body">
        @if($icon)
            <span class="{{ $iconClass }} mb-2"><x-ui.icon :name="$icon" /></span>
        @endif
        @if(! empty($data['title']))
            <{{ $titleTag }} class="site-card-title"@pbField('title')>{{ $data['title'] }}</{{ $titleTag }}>
        @endif
        @if(! empty($data['content']))
            <p class="site-card-text"@pbField('content')>{{ $data['content'] }}</p>
        @endif
        @if($url)
            <a href="{{ $url }}" class="site-link-arrow"{{ \App\Support\BlockDesign::linkAttributes($newTab, $data['link_aria_label'] ?? null) }}>
                {{ ! empty($data['link_text']) ? $data['link_text'] : __('Read more') }}{{ \App\Support\BlockDesign::newTabHint($newTab) }} <x-ui.icon name="arrow-right" />
            </a>
        @endif
    </div>
</div>
