<x-app-layout>
    <x-slot name="header">{{ __('multilingual::ml.texts_title') }}</x-slot>

    <x-ui.page :title="__('multilingual::ml.texts_title')" :lead="__('multilingual::ml.texts_lead')">
        <x-slot:actions>
            <x-ui.locale-switch :original="false" />
            <x-ui.button size="md" icon="arrow-left" :href="route('backend.multilingual.settings')">{{ __('multilingual::ml.title') }}</x-ui.button>
            <x-ui.button variant="primary" size="md" icon="check" type="submit" form="ml-texts-form">{{ __('translation.save') }}</x-ui.button>
        </x-slot:actions>

        <x-ui.note tone="accent" icon="globe" :title="__('translation.locales.translating_title', ['language' => $language])">
            {{ __('multilingual::ml.texts_hint', ['original' => $base]) }}
        </x-ui.note>

        @if(empty($groups))
            <x-ui.card>
                <x-ui.empty icon="languages" :title="__('multilingual::ml.texts_empty')" />
            </x-ui.card>
        @else
            <form id="ml-texts-form" action="{{ route('backend.multilingual.texts.update') }}" method="POST" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="_lang" value="{{ $locale }}">

                @php
                    // Each group gets its own icon and tone so the cards are
                    // distinguishable: core settings with a cog, groups from
                    // plugins (namespace "plugin::") with a puzzle piece in alternating tones.
                    $pluginTones = ['violet', 'teal', 'caution', 'accent'];
                    $pluginIndex = 0;
                @endphp

                @foreach($groups as $groupName => $entries)
                    @php
                        $fromPlugin = str_contains($groupKeys[$groupName] ?? '', '::');
                        $groupIcon = $fromPlugin ? 'puzzle' : 'settings';
                        $groupTone = $fromPlugin ? $pluginTones[$pluginIndex++ % count($pluginTones)] : 'accent';
                    @endphp

                    <x-ui.card :title="$groupName" :icon="$groupIcon" :tone="$groupTone">
                        <div class="flex flex-col gap-4">
                            @foreach($entries as $key => $entry)
                                @php($field = str_replace('.', '__', $key))

                                @if($entry['type'] === 'list')
                                    <div class="flex flex-col gap-3">
                                        <p class="text-sm font-semibold text-fg">{{ __($entry['label']) }}</p>

                                        @forelse($entry['items'] as $index => $item)
                                            @php($id = (string) ($item[$entry['item_key']] ?? $index))
                                            <div class="rounded-lg border border-line p-4">
                                                <p class="mb-3 text-sm font-medium text-fg">{{ $item[$entry['item_label']] ?? $id }}</p>
                                                <div class="ui-grid">
                                                    @foreach($entry['fields'] as $subField => $subLabel)
                                                        <x-ui.field :label="__($subLabel)" :help="filled($item[$subField] ?? null) ? __('translation.locales.original').': '.$item[$subField] : null">
                                                            <x-ui.textarea
                                                                :name="'lists['.$field.']['.$id.']['.$subField.']'"
                                                                rows="2"
                                                                :placeholder="$item[$subField] ?? ''"
                                                            >{{ old('lists.'.$field.'.'.$id.'.'.$subField, $entry['texts'][$id][$subField] ?? '') }}</x-ui.textarea>
                                                        </x-ui.field>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <p class="ui-help">{{ __('multilingual::ml.texts_list_empty') }}</p>
                                        @endforelse
                                    </div>
                                @else
                                    <x-ui.field
                                        :name="'texts.'.$field"
                                        :label="__($entry['label'])"
                                        :help="filled($entry['original']) ? __('translation.locales.original').': '.\Illuminate\Support\Str::limit(strip_tags($entry['original']), 160) : __('multilingual::ml.texts_no_original')"
                                        class="ui-grid-full"
                                    >
                                        @if($entry['type'] === 'textarea')
                                            <x-ui.textarea :name="'texts['.$field.']'" rows="3" :placeholder="$entry['original']">{{ old('texts.'.$field, $entry['translated']) }}</x-ui.textarea>
                                        @else
                                            <x-ui.input :name="'texts['.$field.']'" :value="old('texts.'.$field, $entry['translated'])" :placeholder="$entry['original']" />
                                        @endif
                                    </x-ui.field>
                                @endif
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach

                <div class="flex justify-end">
                    <x-ui.button variant="primary" size="md" icon="check" type="submit">{{ __('translation.save') }}</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.page>
</x-app-layout>
