@props(['model' => null, 'locale' => null])

{{--
    In the translation editor: don't start from scratch.

    No version in this language yet, but already in another
    translation → "Copy from German" (copies all fields except the
    slug). A version copied this way is marked until someone saves
    it.

    The action runs through a script (ui/translation-copy.js) instead of its
    own form: the notice often sits inside the editor form, and the
    browser closes nested forms prematurely.
--}}
@if($model && $locale && $model->exists && method_exists($model, 'translationDraftOf'))
    @php
        $locales = app(\App\Support\Locales::class);
        $drafts = $model->translationDrafts();
        $draft = $drafts[$locale] ?? null;
        $sources = [];

        if (! $draft) {
            $translated = $model->translatedLocales();
            if (! in_array($locale, $translated, true)) {
                // Versions that were only copied themselves are no good as a source
                $sources = array_values(array_filter(
                    $translated,
                    fn ($code) => $code !== $locale && $code !== $locales->base() && $locales->has($code) && ! isset($drafts[$code]),
                ));
            }
        }

        // Signature without host (stable behind proxies); url() prepends the
        // installation's base path (e.g. /cms/public). The return path is relative
        // to the application so that redirect()->to() does not get it twice.
        $query = request()->getQueryString();
        $back = '/'.ltrim(request()->path(), '/').($query ? '?'.$query : '');
        $copyUrl = fn (string $from) => url(\Illuminate\Support\Facades\URL::temporarySignedRoute('backend.translations.copy', now()->addHours(2), [
            'type' => $model::class,
            'id' => $model->getKey(),
            'from' => $from,
            'to' => $locale,
            'user' => auth()->id(),
            'back' => $back,
        ], absolute: false));
    @endphp

    @if($draft)
        <x-ui.note tone="caution" icon="copy" {{ $attributes }} :title="__('translation.locales.draft_title', ['language' => $locales->native($draft)])">
            {{ __('translation.locales.draft_text', ['language' => $locales->native($draft)]) }}
        </x-ui.note>
    @elseif($sources)
        <x-ui.note tone="neutral" icon="copy" {{ $attributes }} :title="__('translation.locales.copy_title')">
            <p>{{ __('translation.locales.copy_text', ['language' => $locales->native($locale)]) }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($sources as $code)
                    <x-ui.button size="sm" icon="copy" :data-translation-copy="$copyUrl($code)">{{ __('translation.locales.copy_from', ['language' => $locales->native($code)]) }}</x-ui.button>
                @endforeach
            </div>
        </x-ui.note>
    @endif
@endif
