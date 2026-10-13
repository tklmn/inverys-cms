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
    // Static notice: non-assertive "status" by default; "alert" interrupts and only when chosen
    $role = \App\Support\BlockDesign::alertRole($settings['role'] ?? null);
    // Title as a heading only when a level is chosen — otherwise a paragraph, as before
    $titleTag = \App\Support\BlockDesign::headingLevel($settings['title_level'] ?? null, 'p');
@endphp
<div class="block-alert site-alert site-alert-{{ $type }} {{ $settings['class'] ?? '' }}" @if($role) role="{{ $role }}" @endif>
    <span class="site-alert-icon"><x-ui.icon :name="$icon" /></span>
    <div class="site-alert-body">
        @if(! empty($data['title']))
            <{{ $titleTag }} class="site-alert-title"@pbField('title')>{{ $data['title'] }}</{{ $titleTag }}>
        @endif
        @if(! empty($data['content']))
            <p class="site-alert-text"@pbField('content')>{{ $data['content'] }}</p>
        @endif
    </div>
</div>
