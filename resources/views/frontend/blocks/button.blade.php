@php
    $style = in_array($data['style'] ?? 'primary', ['primary', 'secondary', 'outline', 'ghost', 'light', 'danger'], true)
        ? ($data['style'] ?? 'primary')
        : 'primary';
    $size = match($settings['size'] ?? '') { 'lg' => 'site-btn-lg', 'sm' => 'site-btn-sm', default => '' };
    $icon = \App\Support\ContentIcons::has($data['icon'] ?? null) ? $data['icon'] : null;
    $newWindow = ($data['target'] ?? '_self') === '_blank';
    $align = match($settings['align'] ?? '') { 'center' => 'justify-center', 'right' => 'justify-end', 'full' => 'full', default => '' };
@endphp
@if($align !== '')<div class="flex {{ $align === 'full' ? '' : $align }}">@endif
<a
    href="{{ \App\Helpers\HtmlHelper::safeUrl($data['url'] ?? '#') }}"
    target="{{ $data['target'] ?? '_self' }}"
    @if($newWindow) rel="noopener noreferrer" @endif
    class="block-button site-btn site-btn-{{ $style }} {{ $size }} {{ $align === 'full' ? 'w-full' : '' }} {{ $settings['class'] ?? '' }}"
>
    <span @pbField('text')>{{ $data['text'] ?? __('translation.builder.default_button') }}</span>
    @if($icon)
        <x-ui.icon :name="$icon" />
    @elseif($newWindow)
        <x-ui.icon name="external-link" />
    @endif
</a>
@if($align !== '')</div>@endif
