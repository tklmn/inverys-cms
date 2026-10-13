{{--
    Field label. The asterisk is only visual (aria-hidden): the input itself
    carries "required" and the form explains the asterisk at the top.

    @param string $for       id of the input
    @param string $label
    @param bool   $required
--}}
<label for="{{ $for }}" class="block text-sm font-medium text-gray-700 mb-1">
    {{ $label }}
    @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
</label>
