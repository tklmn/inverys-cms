@props(['name'])

{{--
    Navigation icon. Core entries use Lucide names; plugins may still
    pass Bootstrap names (bi-…) — App\Support\Icon translates them
    via its alias table so existing extensions get a real icon without
    changes instead of an empty box.
--}}
<x-ui.icon :name="$name" {{ $attributes }} />
