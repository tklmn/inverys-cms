{{--
    New page: title, parent page and starter template — nothing more is needed,
    everything else is then in the editor. New pages start as a draft.

    In the page list's dialog and on backend.pages.create (without JavaScript,
    direct call).

    @param array $parentOptions
    @param array $starters  App\Support\PageStarters::all()
    @param string $idPrefix  unique IDs in case the page contains the form twice
--}}
@php($idPrefix = $idPrefix ?? 'new-page')
<form action="{{ route('backend.pages.store') }}" method="POST" class="flex flex-col gap-5" id="{{ $idPrefix }}-form">
    @csrf

    <x-ui.field name="title" :label="__('translation.pages.field_title')" :id="$idPrefix.'-title'">
        <x-ui.input :id="$idPrefix.'-title'" name="title" type="text" :value="old('title')" required autofocus maxlength="255" />
    </x-ui.field>

    <x-ui.field name="parent_id" :label="__('translation.pages.parent')" :id="$idPrefix.'-parent'">
        <x-ui.select :id="$idPrefix.'-parent'" name="parent_id">
            @foreach($parentOptions as $value => $label)
                <option value="{{ $value }}" @selected(old('parent_id', request('parent')) == $value)>{{ $label }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.field>

    <fieldset class="flex flex-col gap-2">
        <legend class="ui-label mb-2">{{ __('translation.pages.starter') }}</legend>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach($starters as $key => $starter)
                <x-ui.option-card
                    name="starter"
                    :value="$key"
                    :title="$starter['label']"
                    :description="$starter['text']"
                    :icon="$starter['icon']"
                    :checked="old('starter', 'blank') === $key"
                />
            @endforeach
        </div>
    </fieldset>

    <p class="ui-help">{{ __('translation.pages.new_page_draft_hint') }}</p>

    <div class="flex flex-wrap justify-end gap-2">
        {{ $cancel ?? '' }}
        <x-ui.button variant="primary" size="md" icon="arrow-right" type="submit">{{ __('translation.pages.create_and_edit') }}</x-ui.button>
    </div>
</form>
