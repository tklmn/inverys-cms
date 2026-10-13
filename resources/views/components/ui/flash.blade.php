{{--
    Messages that have to stay in the page flow.

    Success and status messages go through x-ui.toasts and fade out on their
    own. Only validation errors remain here: they belong to the form, not
    in a popup that is gone after five seconds.
--}}
@if($errors->any())
    <x-ui.note tone="critical" :title="__('translation.validation_failed')">
        <ul class="list-disc ps-4">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-ui.note>
@endif
