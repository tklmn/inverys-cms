@props(['form'])

{{--
    Notice at the top of the editor while a translation is being edited:
    which language, what empty fields mean, why fields are locked.
--}}
@if($form->locale)
    @php($locales = app(\App\Support\Locales::class))
    <x-ui.note tone="accent" icon="globe" :title="__('translation.locales.translating_title', ['language' => $locales->native($form->locale)])">
        {{ __('translation.locales.translating_text', ['original' => $locales->native($locales->base())]) }}
    </x-ui.note>
    <x-ui.translation-source :model="$form->model" :locale="$form->locale" />
@endif
