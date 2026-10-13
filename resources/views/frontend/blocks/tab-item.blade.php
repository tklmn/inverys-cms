<div id="tabpanel-{{ $id }}" role="tabpanel" aria-labelledby="tab-{{ $id }}" class="text-[var(--site-muted)] {{ $settings['class'] ?? '' }}">
    @if(! empty($children))
        {!! $children !!}
    @endif
</div>
