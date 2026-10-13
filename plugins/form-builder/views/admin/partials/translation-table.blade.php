{{--
    Translation of a form: only the texts visitors see.
    The field list itself applies to all languages and is edited in the
    original. Each row shows the original as a placeholder; empty = original.

    @param \Plugins\FormBuilder\Models\Form $form
    @param string $locale
--}}
@php
    $saved = json_decode($form->translationsFor($locale)['field_texts'] ?? '[]', true) ?: [];
    $translation = $form->translationsFor($locale);
    $settings = $form->getOriginalValue('settings') ?? [];

    $labels = [
        'label' => __('form-builder::forms.label'),
        'placeholder' => __('form-builder::forms.placeholder'),
        'help_text' => __('form-builder::forms.help_text'),
        'content' => __('form-builder::forms.content'),
        'error_message' => __('form-builder::forms.error_message'),
    ];
@endphp

<x-ui.card :title="__('form-builder::forms.translation_fields')" icon="languages" flush>
    <x-ui.list>
        @forelse($form->getFlatFields() as $field)
            @php
                $key = \Plugins\FormBuilder\Models\Form::textKey($field);
                $texts = collect(\Plugins\FormBuilder\Models\Form::TRANSLATABLE_FIELD_KEYS)
                    ->filter(fn ($k) => filled($field[$k] ?? null));
                $options = $field['options'] ?? [];

                // Field type as the label; types from other plugins (captcha) without their own key stay raw
                $type = (string) ($field['type'] ?? '');
                $typeLabel = \Illuminate\Support\Facades\Lang::has('form-builder::forms.type_'.$type)
                    ? __('form-builder::forms.type_'.$type)
                    : $type;
            @endphp

            @continue($key === null || ($field['type'] ?? '') === 'hidden' || ($texts->isEmpty() && empty($options)))

            <div class="ui-list-row block" role="listitem">
                <p class="mb-3 flex items-center gap-2 text-sm font-medium text-fg">
                    <x-ui.badge :dot="false">{{ $typeLabel }}</x-ui.badge>
                    <span class="font-mono text-xs text-fg-muted">{{ $field['name'] ?? $key }}</span>
                </p>

                <div class="ui-grid">
                    @foreach($texts as $textKey)
                        <x-ui.field :name="'texts.'.$key.'.'.$textKey" :label="$labels[$textKey]">
                            @if($textKey === 'content')
                                <x-ui.textarea
                                    :name="'texts['.$key.']['.$textKey.']'"
                                    rows="3"
                                    :placeholder="$field[$textKey]"
                                >{{ old('texts.'.$key.'.'.$textKey, $saved[$key][$textKey] ?? '') }}</x-ui.textarea>
                            @else
                                <x-ui.input
                                    :name="'texts['.$key.']['.$textKey.']'"
                                    :value="old('texts.'.$key.'.'.$textKey, $saved[$key][$textKey] ?? '')"
                                    :placeholder="$field[$textKey]"
                                />
                            @endif
                        </x-ui.field>
                    @endforeach

                    @foreach($options as $option)
                        @php($value = (string) ($option['value'] ?? ''))
                        <x-ui.field :label="__('form-builder::forms.option_text').' “'.$value.'”'">
                            <x-ui.input
                                :name="'texts['.$key.'][options]['.$value.']'"
                                :value="old('texts.'.$key.'.options.'.$value, $saved[$key]['options'][$value] ?? '')"
                                :placeholder="$option['label'] ?? $value"
                            />
                        </x-ui.field>
                    @endforeach
                </div>
            </div>
        @empty
            <x-ui.empty icon="list">{{ __('form-builder::forms.click_to_add') }}</x-ui.empty>
        @endforelse
    </x-ui.list>
</x-ui.card>

<x-ui.card :title="__('form-builder::forms.translation_messages')" icon="send" tone="teal">
    <div class="ui-grid">
        <x-ui.field name="submit_label" :label="__('form-builder::forms.submit_label')" optional>
            <x-ui.input name="submit_label" :value="old('submit_label', $translation['submit_label'] ?? '')" :placeholder="($settings['submit_label'] ?? '') ?: __('form-builder::forms.submit')" />
        </x-ui.field>

        <x-ui.field name="success_message" :label="__('form-builder::forms.success_message')" optional>
            <x-ui.input name="success_message" :value="old('success_message', $translation['success_message'] ?? '')" :placeholder="($settings['success_message'] ?? '') ?: __('form-builder::forms.default_success_message')" />
        </x-ui.field>

        <x-ui.field name="accessible_name" :label="__('form-builder::forms.accessible_name')" :help="__('form-builder::forms.accessible_name_hint')" class="ui-grid-full" optional>
            <x-ui.input name="accessible_name" :value="old('accessible_name', $translation['accessible_name'] ?? '')" :placeholder="($settings['accessible_name'] ?? '') ?: $form->name" />
        </x-ui.field>
    </div>
</x-ui.card>
