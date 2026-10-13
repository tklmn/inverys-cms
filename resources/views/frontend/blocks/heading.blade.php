@php
    $level = in_array($data['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? ($data['level'] ?? 'h2') : 'h2';
    $alignClass = match($data['alignment'] ?? 'left') {
        'center' => 'text-center',
        'right' => 'text-right',
        default => '',
    };
    $sizeClass = match($level) {
        'h1' => 'text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.08]',
        'h2' => 'text-3xl sm:text-4xl font-bold tracking-tight leading-tight',
        'h3' => 'text-2xl font-semibold tracking-tight',
        'h4' => 'text-xl font-semibold',
        'h5' => 'text-lg font-semibold',
        'h6' => 'text-base font-semibold uppercase tracking-wide',
    };
    $gradient = ($settings['style'] ?? '') === 'gradient';
    $eyebrow = trim((string) ($data['eyebrow'] ?? ''));
    $groupAlign = match($data['alignment'] ?? 'left') { 'center' => 'site-text-center', 'right' => 'site-text-right', default => '' };
@endphp
@if($eyebrow !== '')
<div class="site-heading-group mb-4 {{ $groupAlign }}">
    <span class="site-eyebrow"@pbField('eyebrow')>{{ $eyebrow }}</span>
@endif
<{{ $level }} class="block-heading text-balance {{ $sizeClass }} {{ $alignClass }} {{ $eyebrow !== '' ? '' : 'mb-4' }} {{ $settings['class'] ?? '' }}"@pbField('text')>
    @if($gradient)<span class="site-text-gradient">{{ $data['text'] ?? '' }}</span>@else{{ $data['text'] ?? '' }}@endif
</{{ $level }}>
@if($eyebrow !== '')
</div>
@endif
