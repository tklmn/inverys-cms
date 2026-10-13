@props([
    'user',
    'size' => 'sm',
])

{{--
    Profile picture or initials of a person.

    The colour of the initials is tied to the person (computed from the email
    address) — so you recognise them in every list, and the list is no
    longer uniformly grey.
--}}
@php
    $tones = ['accent', 'violet', 'teal', 'positive', 'caution'];
    $tone = $tones[crc32((string) $user->email) % count($tones)];
    $initials = Str::upper(Str::substr((string) $user->first_name, 0, 1).Str::substr((string) $user->last_name, 0, 1))
        ?: Str::upper(Str::substr((string) $user->name, 0, 2));
@endphp

@if($user->avatar)
    <img src="{{ \App\Helpers\StorageHelper::url($user->avatar) }}" alt="" {{ $attributes->merge(['class' => 'ui-avatar ui-avatar-'.$size.' object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'ui-avatar ui-avatar-'.$size.' ui-tint-'.$tone]) }} aria-hidden="true">{{ $initials }}</span>
@endif
