@props([
    'model' => null,
    // false: only translations to choose from (pages that do not edit the original)
    'original' => true,
])

{{--
    Language switch in the editor: which version is being edited?

    Dropdown like the website's language menu (same icon, code in the
    button) — stays the same width with any number of languages. Each language
    shows its state: original, translated, copied from another language
    or not translated yet.

    Only appears with the multilingual plugin and more than one language.
    The choice is kept in the URL (?lang=en) so that reloading and the back
    button show the same version.
--}}
@if(\App\Support\EditLocale::available())
    @php
        $locales = app(\App\Support\Locales::class);
        $base = $locales->base();
        $editing = \App\Support\EditLocale::current() ?? $base;
        $translated = $model && method_exists($model, 'translatedLocales') ? $model->translatedLocales() : [];

        $entries = [];
        foreach ($locales->all() as $code => $meta) {
            if (! $original && $code === $base) {
                continue;
            }

            // Without a model (e.g. theme texts) there is nothing to count — no state
            $draft = $model && $code !== $base && method_exists($model, 'translationDrafts') ? ($model->translationDrafts()[$code] ?? null) : null;
            $state = match (true) {
                $code === $base => 'original',
                ! $model => null,
                $draft !== null => 'draft',
                in_array($code, $translated, true) => 'done',
                default => 'missing',
            };

            $entries[$code] = [
                'native' => $meta['native'],
                'url' => $code === $base ? request()->fullUrlWithoutQuery(['lang']) : request()->fullUrlWithQuery(['lang' => $code]),
                'state' => $state,
                'note' => match ($state) {
                    'original' => __('translation.locales.original'),
                    'draft' => __('translation.locales.draft', ['language' => $locales->native($draft)]),
                    'missing' => __('translation.locales.missing'),
                    'done' => __('translation.locales.translated'),
                    default => null,
                },
            ];
        }

        $current = $entries[$editing] ?? null;
    @endphp

    <details class="ui-menu" data-menu>
        <summary
            class="ui-btn ui-btn-md ui-btn-ghost ui-menu-trigger"
            title="{{ __('translation.locales.editing') }}: {{ $locales->native($editing) }}{{ ($current['note'] ?? null) ? ' ('.$current['note'].')' : '' }}"
        >
            <x-ui.icon name="languages" />
            {{-- Screen reader name contains the visible text (WCAG 2.5.3) --}}
            <span class="sr-only">{{ __('translation.locales.editing') }}: {{ $locales->native($editing) }}{{ ($current['note'] ?? null) ? ' ('.$current['note'].')' : '' }},</span>
            <span class="uppercase">{{ $editing }}</span>
            @if(in_array($current['state'] ?? null, ['missing', 'draft'], true))<span class="ui-menu-dot" aria-hidden="true"></span>@endif
        </summary>

        <ul class="ui-menu-list">
            @foreach($entries as $code => $entry)
                <li>
                    <a
                        href="{{ $entry['url'] }}"
                        class="ui-menu-item"
                        hreflang="{{ $code }}"
                        @if($code === $editing) aria-current="true" @endif
                        @if($entry['state']) data-state="{{ $entry['state'] }}" @endif
                    >
                        <span class="ui-menu-item-main">
                            <span class="ui-menu-item-label" lang="{{ $code }}">{{ $entry['native'] }}</span>
                            @if($entry['note'])<span class="ui-menu-item-note">{{ $entry['note'] }}</span>@endif
                        </span>
                        <span class="ui-menu-item-code">{{ $code }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
