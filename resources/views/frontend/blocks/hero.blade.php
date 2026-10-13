@php
    // Large page intro: eyebrow, title, text, two buttons, image.
    // Everything configurable via the block options — no CSS classes needed.
    $layout = ($settings['layout'] ?? 'split') === 'centered' ? 'centered' : 'split';
    $surface = \App\Support\BlockDesign::surface($settings['style'] ?? 'glow');
    $size = in_array($settings['size'] ?? '', ['large', 'compact'], true) ? $settings['size'] : '';
    // H2 for calls to action further down the page — only one H1 per page
    $tag = ($settings['level'] ?? 'h1') === 'h2' ? 'h2' : 'h1';
    $grid = (bool) ($settings['grid'] ?? true);
    $anchor = \App\Support\BlockDesign::anchor($settings['anchor'] ?? '');
    $bg = \App\Support\BlockDesign::background($settings);
    $image =trim((string) ($data['image'] ?? ''));
    $icon = \App\Support\ContentIcons::has($data['primary_icon'] ?? null) ? $data['primary_icon'] : 'arrow-right';
@endphp
<section @if($anchor) id="{{ $anchor }}" @endif class="block-hero site-hero site-hero-{{ $layout }} {{ $size ? 'site-hero-'.$size : '' }} {{ $grid ? 'site-hero-grid' : '' }} {{ $surface }} {{ $bg['class'] }} {{ $settings['class'] ?? '' }}">
    @if($bg['src'])<img src="{{ $bg['src'] }}" alt="" class="site-bg-media" loading="eager">@endif
    <div class="site-container site-hero-inner">
        <div class="site-hero-content">
            @if(! empty($data['eyebrow']))
                <span class="site-eyebrow"@pbField('eyebrow')>{{ $data['eyebrow'] }}</span>
            @endif
            @if(! empty($data['title']))
                <{{ $tag }} class="site-hero-title"@pbField('title')>{{ $data['title'] }}</{{ $tag }}>
            @endif
            @if(! empty($data['text']))
                <p class="site-hero-text"@pbField('text')>{{ $data['text'] }}</p>
            @endif
            @if(! empty($data['primary_text']) || ! empty($data['secondary_text']))
                <div class="site-hero-actions">
                    @if(! empty($data['primary_text']))
                        <a href="{{ \App\Helpers\HtmlHelper::safeUrl($data['primary_url'] ?? '#') }}" class="site-btn site-btn-primary site-btn-lg">
                            <span @pbField('primary_text')>{{ $data['primary_text'] }}</span> <x-ui.icon :name="$icon" />
                        </a>
                    @endif
                    @if(! empty($data['secondary_text']))
                        <a href="{{ \App\Helpers\HtmlHelper::safeUrl($data['secondary_url'] ?? '#') }}" class="site-btn site-btn-secondary site-btn-lg">
                            <span @pbField('secondary_text')>{{ $data['secondary_text'] }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if($image !== '' && $layout === 'split')
            <div class="site-hero-media">
                <img src="{{ Str::startsWith($image, ['http://', 'https://']) ? $image : asset($image) }}" alt="{{ $data['image_alt'] ?? '' }}" loading="eager">
            </div>
        @endif
    </div>
</section>
