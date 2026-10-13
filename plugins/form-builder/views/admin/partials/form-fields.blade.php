@props(['form' => null])

@php
    $editing = $form !== null;
    $s = fn (string $key, $fallback = null) => old('settings_'.$key, $editing ? ($form->settings[$key] ?? $fallback) : $fallback);

    // What applies when the field stays empty — the same order as when sending
    // (form, then global form setting, then system)
    $fallbackRecipients = cms()->settings()->get('form_builder.default_recipients', '') ?: __('form-builder::forms.recipients_placeholder');
    $fallbackFromName = cms()->settings()->get('form_builder.from_name', '') ?: config('app.name');
    $fallbackFromEmail = cms()->settings()->get('form_builder.from_email', '') ?: config('mail.from.address');
@endphp

<x-ui.card :title="__('form-builder::forms.general')" icon="clipboard-list">
    <div class="ui-grid">
        <x-ui.field name="name" :label="__('form-builder::forms.form_name')">
            <x-ui.input id="name" name="name" :value="old('name', $form?->name)" required autofocus />
        </x-ui.field>

        <x-ui.field name="slug" :label="__('form-builder::forms.slug')" optional>
            <x-ui.input id="slug" name="slug" :value="old('slug', $form?->slug)" :placeholder="__('form-builder::forms.auto_generated')" />
        </x-ui.field>

        <x-ui.field name="description" :label="__('form-builder::forms.description')" class="ui-grid-full" optional>
            <x-ui.textarea id="description" name="description" rows="3">{{ old('description', $form?->description) }}</x-ui.textarea>
        </x-ui.field>
    </div>

    <x-ui.switch-row
        class="mt-3"
        name="is_active"
        :label="__('form-builder::forms.active')"
        :checked="old('is_active', $editing ? $form->is_active : '1') == '1'"
        on-value="1"
        off-value="0"
    />
</x-ui.card>

<x-ui.card :title="__('form-builder::forms.fields')" icon="list-checks" tone="violet">
    <div id="form-builder-container"></div>
    <input type="hidden" name="fields" id="fields-input" value="{{ old('fields', $editing ? json_encode($form->fields) : '[]') }}">
    <x-input-error :messages="$errors->get('fields')" class="mt-2" />
</x-ui.card>

<x-ui.card :title="__('form-builder::forms.email_settings')" icon="mail" tone="teal">
    <div class="flex flex-col gap-4">
        <x-ui.switch-row
            name="settings_send_email"
            :label="__('form-builder::forms.send_email')"
            :checked="$s('send_email', '1') == '1'"
            on-value="1"
            off-value="0"
        />

        {{-- Recipients and subject only apply to the notification --}}
        <div class="ui-grid" data-requires="settings_send_email">
            <x-ui.field name="settings_recipients" :label="__('form-builder::forms.recipients')" :help="__('form-builder::forms.recipients_hint')">
                <x-ui.input id="settings_recipients" name="settings_recipients" :value="$s('recipients')" :placeholder="$fallbackRecipients" />
            </x-ui.field>

            <x-ui.field name="settings_email_subject" :label="__('form-builder::forms.email_subject')" optional>
                <x-ui.input id="settings_email_subject" name="settings_email_subject" :value="$s('email_subject')" :placeholder="__('form-builder::forms.email_subject_placeholder')" />
            </x-ui.field>
        </div>

        <x-ui.switch-row
            name="settings_send_copy_to_sender"
            :label="__('form-builder::forms.send_copy_to_sender')"
            :hint="__('form-builder::forms.send_copy_to_sender_hint')"
            :checked="$s('send_copy_to_sender') == '1'"
            on-value="1"
            off-value="0"
        />

        <div data-requires="settings_send_copy_to_sender">
            <x-ui.field name="settings_copy_message" :label="__('form-builder::forms.copy_message')" :help="__('form-builder::forms.copy_message_hint')" optional>
                <x-ui.textarea id="settings_copy_message" name="settings_copy_message" rows="3" maxlength="1000" :placeholder="__('form-builder::forms.sender_copy_text')">{{ $s('copy_message') }}</x-ui.textarea>
            </x-ui.field>
        </div>

        {{--
            Sender and reply-to apply to the notification AND the copy to
            the sender — so they are not tied to the first switch.
        --}}
        <div class="ui-grid">
            <x-ui.field name="settings_from_name" :label="__('form-builder::forms.from_name')" optional>
                <x-ui.input id="settings_from_name" name="settings_from_name" :value="$s('from_name')" :placeholder="$fallbackFromName" />
            </x-ui.field>

            <x-ui.field name="settings_from_email" :label="__('form-builder::forms.from_email')" optional>
                <x-ui.input id="settings_from_email" name="settings_from_email" type="email" :value="$s('from_email')" :placeholder="$fallbackFromEmail" />
            </x-ui.field>

            <x-ui.field name="settings_reply_to" :label="__('form-builder::forms.reply_to')" :help="__('form-builder::forms.reply_to_help')" optional>
                <x-ui.input id="settings_reply_to" name="settings_reply_to" :value="$s('reply_to')" :placeholder="__('form-builder::forms.reply_to_hint')" />
            </x-ui.field>
        </div>
    </div>
</x-ui.card>

<x-ui.card :title="__('form-builder::forms.form_settings')" icon="sliders-horizontal" tone="caution">
    <div class="ui-grid">
        <x-ui.field name="settings_submit_label" :label="__('form-builder::forms.submit_label')" optional>
            <x-ui.input id="settings_submit_label" name="settings_submit_label" :value="$s('submit_label')" :placeholder="__('form-builder::forms.submit')" />
        </x-ui.field>

        <x-ui.field name="settings_success_message" :label="__('form-builder::forms.success_message')" optional>
            <x-ui.input id="settings_success_message" name="settings_success_message" :value="$s('success_message')" :placeholder="__('form-builder::forms.default_success_message')" />
        </x-ui.field>

        {{-- Name screen readers announce for the form (aria-label); empty = form name --}}
        <x-ui.field name="settings_accessible_name" :label="__('form-builder::forms.accessible_name')" :help="__('form-builder::forms.accessible_name_hint')" class="ui-grid-full" optional>
            <x-ui.input id="settings_accessible_name" name="settings_accessible_name" :value="$s('accessible_name')" :placeholder="$form?->name ?: __('form-builder::forms.accessible_name_placeholder')" maxlength="255" />
        </x-ui.field>
    </div>

    <div class="mt-3 flex flex-col">
        <x-ui.switch-row
            name="settings_honeypot"
            :label="__('form-builder::forms.honeypot')"
            :checked="$s('honeypot', '1') == '1'"
            on-value="1"
            off-value="0"
        />

        <x-ui.switch-row
            name="settings_show_confirmation"
            :label="__('form-builder::forms.show_confirmation')"
            :checked="$s('show_confirmation') == '1'"
            on-value="1"
            off-value="0"
        />
    </div>
</x-ui.card>
