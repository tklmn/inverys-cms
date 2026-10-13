@php
    // The scroll area is reachable by keyboard (WCAG 2.1.1) and named for screen readers
    $label = trim((string) ($data['aria_label'] ?? '')) ?: __('translation.frontend.code_example');
@endphp
<div class="block-code my-2 {{ $settings['class'] ?? '' }}">
    <pre class="site-code" tabindex="0" role="region" aria-label="{{ $label }}"><code class="language-{{ $data['language'] ?? 'html' }}">{{ $data['code'] ?? '' }}</code></pre>
</div>
