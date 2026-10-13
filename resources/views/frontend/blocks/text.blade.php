@php
    $align = \App\Support\BlockDesign::align($settings['align'] ?? '');
    $size = match($settings['size'] ?? '') {
        'lead' => 'prose-lg site-lead',
        'small' => 'prose-sm',
        default => 'prose-lg',
    };
    // Readable line length or full width
    $measure = ($settings['measure'] ?? '') === 'readable' ? 'mx-auto max-w-3xl' : 'max-w-none';
@endphp
<div class="block-text site-prose prose {{ $size }} dark:prose-invert {{ $measure }} {{ $align }} {{ $settings['class'] ?? '' }}"@pbField('content', true)>
    {!! \App\Helpers\HtmlHelper::clean($data['content'] ?? '') !!}
</div>
