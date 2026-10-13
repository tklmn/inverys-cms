@props(['model'])

{{--
    Badge in lists: which languages does this entry have already?
    Filled = translated, outlined = missing (visitors see the default
    language there), dashed = copied from another language and
    not saved yet. Only appears with the multilingual plugin.
--}}
@php($status = \App\Support\TranslationStatus::for($model))
@php($drafts = $status ? \App\Support\TranslationStatus::drafts($model) : [])

@if($status)
    @php($locales = app(\App\Support\Locales::class))
    <span {{ $attributes->merge(['class' => 'ui-locale-status']) }}>
        @foreach($status as $code => $done)
            @php($draft = in_array($code, $drafts, true))
            <span
                class="ui-locale-chip"
                data-done="{{ $done && ! $draft ? 'true' : 'false' }}"
                @if($draft) data-draft @endif
                title="{{ $locales->native($code) }} · {{ $draft ? __('translation.locales.draft_short') : ($done ? __('translation.locales.translated') : __('translation.locales.missing')) }}"
            >{{ $code }}</span>
        @endforeach
    </span>
@endif
