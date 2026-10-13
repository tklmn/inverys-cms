<x-app-layout>
    <x-slot name="header">{{ __('multilingual::ml.title') }}</x-slot>

    <x-ui.page :title="__('multilingual::ml.title')" :lead="__('multilingual::ml.lead')">
        @if(count($languages) > 1)
            <x-slot:actions>
                <x-ui.button size="md" icon="type" :href="route('backend.multilingual.texts')">{{ __('multilingual::ml.texts_title') }}</x-ui.button>
            </x-slot:actions>
        @endif

        {{-- Settings on the left, explanations on the right (narrow: below) --}}
        <div class="ui-split">
        <div class="ui-split-main">
        <form action="{{ route('backend.multilingual.settings.update') }}" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <x-ui.card :title="__('multilingual::ml.default_heading')" icon="globe">
                <p class="ui-help mb-4">{{ __('multilingual::ml.default_hint') }}</p>

                <div class="ui-grid ui-grid-dense">
                    @foreach($languages as $code => $meta)
                        @php
                            $count = $counts[$code] ?? 0;
                            $draftCount = $drafts[$code] ?? 0;
                            $description = $code === $base
                                ? __('multilingual::ml.is_base')
                                : trans_choice('multilingual::ml.translated_count', $count, ['count' => $count])
                                    .($draftCount > 0 ? ' · '.trans_choice('multilingual::ml.drafts_count', $draftCount, ['count' => $draftCount]) : '');
                        @endphp
                        <x-ui.option-card
                            name="default_locale"
                            :value="$code"
                            :title="$meta['native']"
                            :description="$description"
                            icon="languages"
                            :tone="$code === $base ? 'teal' : 'accent'"
                            :checked="$code === $default"
                            :badge="$code === $default ? __('multilingual::ml.default_badge') : null"
                        />
                    @endforeach
                </div>

                <x-slot:foot>
                    <div class="flex justify-end">
                        <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                    </div>
                </x-slot:foot>
            </x-ui.card>
        </form>

        {{--
            Prefill a language: an existing translation as a template for
            a new language. The source is only ever a translation, never the
            original (empty fields show it anyway). Not possible → greyed out
            with a reason instead of hidden.
        --}}
        @php
            $targets = array_values(array_diff(array_keys($languages), [$base]));
            $sources = array_values(array_filter($targets, fn ($code) => ($counts[$code] ?? 0) > 0));
            $prefillBlocked = count($targets) < 2
                ? __('multilingual::ml.prefill_needs_two')
                : ($sources === [] ? __('multilingual::ml.prefill_no_source') : null);

            // Preselection: source with the most content, target with the least
            $fromDefault = collect($sources)->sortByDesc(fn ($code) => $counts[$code] ?? 0)->first();
            $toDefault = collect($targets)->reject(fn ($code) => $code === $fromDefault)->sortBy(fn ($code) => $counts[$code] ?? 0)->first();
        @endphp

        <form action="{{ route('backend.multilingual.copy') }}" method="POST" data-confirm="{{ __('multilingual::ml.prefill_confirm') }}">
            @csrf
            <x-ui.card :title="__('multilingual::ml.prefill_heading')" icon="copy" tone="accent">
                <p class="ui-help mb-4">{{ __('multilingual::ml.prefill_hint') }}</p>

                <div class="ui-grid">
                    <x-ui.field name="from" :label="__('multilingual::ml.prefill_from')">
                        <x-ui.select id="from" name="from" :disabled="$prefillBlocked !== null">
                            @foreach($sources ?: $targets as $code)
                                <option value="{{ $code }}" @selected(old('from', $fromDefault) === $code)>{{ $languages[$code]['native'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field name="to" :label="__('multilingual::ml.prefill_to')">
                        <x-ui.select id="to" name="to" :disabled="$prefillBlocked !== null">
                            @foreach($targets as $code)
                                <option value="{{ $code }}" @selected(old('to', $toDefault) === $code)>{{ $languages[$code]['native'] }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                <x-slot:foot>
                    <div class="flex justify-end">
                        <x-ui.button size="md" icon="copy" type="submit" :disabled="$prefillBlocked !== null" :disabled-reason="$prefillBlocked">{{ __('multilingual::ml.prefill_button') }}</x-ui.button>
                    </div>
                </x-slot:foot>
            </x-ui.card>
        </form>

        </div>

        {{-- Both explanation cards use the same text style (ui-help) --}}
        <div class="ui-split-aside">
        <x-ui.card :title="__('multilingual::ml.how_heading')" icon="info" tone="teal">
            <div class="flex flex-col gap-2">
                <p class="ui-help">{!! __('multilingual::ml.how_base', ['base' => '<strong class="text-fg">'.e($languages[$base]['native'] ?? $base).'</strong>']) !!}</p>
                <p class="ui-help">{{ __('multilingual::ml.how_edit') }}</p>
                <p class="ui-help">{{ __('multilingual::ml.how_fallback') }}</p>
                <p class="ui-help">{{ __('multilingual::ml.how_urls') }}</p>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('multilingual::ml.packs_heading')" icon="package" tone="violet">
            <div class="flex flex-col items-start gap-3">
                <p class="ui-help">{{ __('multilingual::ml.packs_hint') }}</p>

                @if($packs !== [])
                    <ul class="flex w-full flex-col gap-2" aria-label="{{ __('multilingual::ml.packs_installed') }}">
                        @foreach($packs as $pack)
                            <li class="flex items-center gap-2 text-sm">
                                <x-ui.icon name="languages" class="text-fg-muted" />
                                <span class="min-w-0 flex-1 truncate text-fg">{{ $pack['name'] }}</span>
                                <span class="font-mono text-xs text-fg-muted">v{{ $pack['version'] }}</span>
                                <x-ui.badge :tone="$pack['enabled'] ? 'positive' : 'neutral'">{{ $pack['enabled'] ? __('Enabled') : __('Disabled') }}</x-ui.badge>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @permission('manage-plugins')
                    <x-ui.button icon="plug" :href="route('backend.plugins.index', ['type' => 'language'])">{{ __('multilingual::ml.packs_button') }}</x-ui.button>
                @endpermission
            </div>
        </x-ui.card>
        </div>
        </div>
    </x-ui.page>
</x-app-layout>
