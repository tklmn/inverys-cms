{{--
    Data list. Responds to its own width via container query and therefore
    works unchanged on a full page, in a narrow dashboard column or in the
    detail panel.
--}}
<div {{ $attributes->merge(['class' => 'ui-list']) }} role="list">
    {{ $slot }}
</div>
