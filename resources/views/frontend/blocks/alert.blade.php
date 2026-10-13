@php
    // The editor stores "danger", older content "error"
    $type = match($data['type'] ?? 'info') {
        'success' => 'success',
        'warning' => 'warning',
        'danger', 'error' => 'danger',
        default => 'info',
    };
    $icon = match($type) {
        'success' => 'circle-check',
        'warning' => 'triangle-alert',
        'danger' => 'circle-x',
        default => 'info',
    };
@endphp
<div class="block-alert site-alert site-alert-{{ $type }} {{ $settings['class'] ?? '' }}" role="{{ in_array($type, ['warning', 'danger'], true) ? 'alert' : 'status' }}">
    <span class="site-alert-icon"><x-ui.icon :name="$icon" /></span>
    <div class="site-alert-body">
        @if(! empty($data['title']))
            <p class="site-alert-title"@pbField('title')>{{ $data['title'] }}</p>
        @endif
        @if(! empty($data['content']))
            <p class="site-alert-text"@pbField('content')>{{ $data['content'] }}</p>
        @endif
    </div>
</div>
