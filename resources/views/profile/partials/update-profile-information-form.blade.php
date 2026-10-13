{{--
    Profile, settings and picture — one form, saved via the
    button in the page header (form="profile-form").
--}}
@if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <x-ui.note tone="caution" :title="__('translation.users.email_verification_required')" class="mb-4">
        <p>{{ __('translation.users.email_unverified') }}</p>
        <x-ui.button size="sm" icon="send" type="submit" form="send-verification" class="mt-2">{{ __('translation.users.resend_verification') }}</x-ui.button>
    </x-ui.note>
@endif

@php
    $pendingEmail = \App\Support\EmailChange::pending($user);
@endphp
@if($pendingEmail)
    <form id="cancel-email-change" method="post" action="{{ route('email-change.cancel') }}">
        @csrf
    </form>
    <x-ui.note tone="accent" icon="mail" :title="__('translation.email_change.pending_title')" class="mb-4">
        <p>{{ __('translation.email_change.pending_text', ['new' => $pendingEmail['email'], 'old' => $user->email]) }}</p>
        <x-ui.button size="sm" icon="x" type="submit" form="cancel-email-change" class="mt-2">{{ __('translation.email_change.cancel_button') }}</x-ui.button>
    </x-ui.note>
@endif

<form id="profile-form" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
    @csrf
    @method('patch')

    <x-ui.card :title="__('translation.users.personal_information')" icon="user-round" tone="accent">
        <div class="flex flex-col gap-4">
            {{--
                Profile picture as the first row of the personal details instead of its own
                side card: that way the form stays in one column and no empty
                area appears on the right next to the long cards.
            --}}
            <div class="ui-avatar-field">
                <x-ui.avatar :user="$user" size="lg" />

                <div class="ui-avatar-field-main">
                    <x-ui.dropzone
                        name="avatar"
                        accept="image/*"
                        compact
                        :label="__('translation.users.upload_new_avatar')"
                        :hint="__('translation.users.file_formats')"
                    />
                    <x-input-error :messages="$errors->get('avatar')" />
                </div>

                {{-- Delete always visible, greyed out while there is no picture --}}
                <x-ui.button
                    variant="danger"
                    icon="trash-2"
                    :disabled="! $user->avatar"
                    :disabled-reason="__('translation.users.no_avatar')"
                    data-open-modal="confirm-avatar-deletion"
                >
                    {{ __('translation.users.delete_avatar') }}
                </x-ui.button>
            </div>

            <div class="ui-grid">
                <x-ui.field name="first_name" :label="__('translation.users.first_name')">
                    <x-ui.input id="first_name" name="first_name" :value="old('first_name', $user->first_name)" autocomplete="given-name" required />
                </x-ui.field>
                <x-ui.field name="last_name" :label="__('translation.users.last_name')">
                    <x-ui.input id="last_name" name="last_name" :value="old('last_name', $user->last_name)" autocomplete="family-name" required />
                </x-ui.field>
            </div>

            <div class="ui-grid">
                <x-ui.field name="name" :label="__('translation.name')">
                    <x-ui.input id="name" name="name" icon="at-sign" :value="old('name', $user->name)" autocomplete="username" required />
                </x-ui.field>
                <x-ui.field name="email" :label="__('translation.email')" :help="__('translation.email_change.field_help')">
                    <x-ui.input id="email" name="email" type="email" icon="mail" :value="old('email', $user->email)" autocomplete="email" required />
                </x-ui.field>
            </div>

            {{-- Only required for a new email address (ProfileUpdateRequest) --}}
            <x-ui.field name="current_password" :label="__('translation.email_change.current_password')" :help="__('translation.email_change.current_password_help')" optional>
                <x-ui.input id="current_password" name="current_password" type="password" icon="lock" autocomplete="current-password" />
            </x-ui.field>

            <div class="ui-grid">
                <x-ui.field name="phone" :label="__('translation.users.phone')" optional>
                    <x-ui.input id="phone" name="phone" type="tel" :value="old('phone', $user->phone)" autocomplete="tel" />
                </x-ui.field>
                <x-ui.field name="job_title" :label="__('translation.users.job_title')" optional>
                    <x-ui.input id="job_title" name="job_title" :value="old('job_title', $user->job_title)" autocomplete="organization-title" />
                </x-ui.field>
            </div>

            <x-ui.field name="bio" :label="__('translation.users.bio')" optional>
                <x-ui.textarea id="bio" name="bio" rows="4" data-count-max="1000">{{ old('bio', $user->bio) }}</x-ui.textarea>
            </x-ui.field>
        </div>
    </x-ui.card>

    {{--
        Personal settings take precedence over the site default (Settings →
        General). "Site default" or an empty field removes the
        personal value — then the profile follows the website again.
    --}}
    @php
        $siteDateFormat = $siteDefaults['date_format'] !== ''
            ? $siteDefaults['date_format']
            : __('translation.users.date_format_language', ['example' => now()->isoFormat('L')]);
    @endphp
    <x-ui.card :title="__('translation.users.settings')" icon="sliders-horizontal" tone="teal">
        <div class="flex flex-col gap-4">
            <p class="ui-help">{{ __('translation.users.settings_hint') }}</p>

            <div class="ui-grid">
                <x-ui.field name="language" :label="__('translation.users.language')">
                    {{-- Installed languages from the language registry (core + language packs) --}}
                    <x-ui.select id="language" name="language">
                        <option value="" @selected((string) old('language', $settings['language'] ?? '') === '')>{{ __('translation.users.site_default', ['value' => $siteDefaults['language']]) }}</option>
                        @foreach(app(\App\Support\Locales::class)->all() as $code => $meta)
                            <option value="{{ $code }}" @selected((string) old('language', $settings['language'] ?? '') === (string) $code)>{{ $meta['native'] }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                @php($timezone = (string) old('timezone', $settings['timezone'] ?? ''))
                <x-ui.field name="timezone" :label="__('translation.users.timezone')" :help="__('translation.users.timezone_hint')">
                    <x-ui.select id="timezone" name="timezone">
                        <option value="" @selected($timezone === '')>{{ __('translation.users.site_default', ['value' => $siteDefaults['timezone']]) }}</option>
                        @foreach(timezone_identifiers_list() as $zone)
                            <option value="{{ $zone }}" @selected($timezone === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>

                <x-ui.field
                    name="date_format"
                    :label="__('translation.users.date_format')"
                    optional
                    :help="__('translation.users.date_format_hint', ['default' => $siteDateFormat])"
                >
                    <x-ui.input id="date_format" name="date_format" class="font-mono" :value="old('date_format', $settings['date_format'] ?? '')" :placeholder="$siteDefaults['date_format'] !== '' ? $siteDefaults['date_format'] : 'd.m.Y'" />
                </x-ui.field>
            </div>
        </div>
    </x-ui.card>
</form>
