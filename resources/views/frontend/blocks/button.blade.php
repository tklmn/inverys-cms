@php
    $style = in_array($data['style'] ?? 'primary', ['primary', 'secondary', 'outline', 'ghost', 'light', 'danger'], true)
        ? ($data['style'] ?? 'primary')
        : 'primary';
    $size = match($settings['size'] ?? '') { 'lg' => 'site-btn-lg', 'sm' => 'site-btn-sm', default => '' };
    $icon = \App\Support\ContentIcons::has($data['icon'] ?? null) ? $data['icon'] : null;
    $newWindow = ($data['target'] ?? '_self') === '_blank';
    $align = match($settings['align'] ?? '') { 'center' => 'justify-center', 'right' => 'justify-end', 'full' => 'full', default => '' };
    $label = trim((string) ($data['aria_label'] ?? ''));
@endphp
@if($align !== '')<div class="flex {{ $align === 'full' ? '' : $align }}">@endif
<a
    href="{{ \App\Helpers\HtmlHelper::safeUrl($data['url'] ?? '#') }}"
    target="{{ $newWindow ? '_blank' : '_self' }}"
    @if($newWindow) rel="noopener noreferrer" @endif
    @if($label !== '') aria-label="{{ $newWindow ? $label.' '.__('translation.frontend.opens_new_tab') : $label }}" @endif
    class="block-button site-btn site-btn-{{ $style }} {{ $size }} {{ $align === 'full' ? 'w-full' : '' }} {{ $settings['class'] ?? '' }}"
>
    <span @pbField('text')>{{ $data['text'] ?? __('translation.builder.default_button') }}</span>{{ \App\Support\BlockDesign::newTabHint($newWindow) }}
    @if($icon)
        <x-ui.icon :name="$icon" />
    @elseif($newWindow)
        <x-ui.icon name="external-link" />
    @endif
</a>
@if($align !== '')</div>@endif
