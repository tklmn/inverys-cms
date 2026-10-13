<div id="tabpanel-{{ $id }}" role="tabpanel" class="text-[var(--site-muted)] {{ $settings['class'] ?? '' }}">
    @if(! empty($children))
        {!! $children !!}
    @endif
</div>
