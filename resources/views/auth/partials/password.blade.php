{{--
    Password field with "Show password".

    @include('auth.partials.password', [
        'name' => 'password',                 // field name, also the id
        'label' => label text,
        'autocomplete' => 'current-password', // or new-password
        'link' => ['url' => …, 'text' => …],  // optional, right of the label
        'autofocus' => false,
        'id' => …,                           // optional, otherwise = name
        'bag' => 'updatePassword',            // optional, the form's error bag
    ])
--}}
@php
    $name = $name ?? 'password';
    $inputId = $id ?? $name;
    $error = $errors->getBag($bag ?? 'default')->first($name);
@endphp
<div class="ui-field" @if($error) data-invalid @endif>
    @if(! empty($link))
        <div class="auth-label-row">
            <label class="ui-label" for="{{ $inputId }}">{{ $label }}</label>
            <a href="{{ $link['url'] }}" class="auth-link">{{ $link['text'] }}</a>
        </div>
    @else
        <label class="ui-label" for="{{ $inputId }}">{{ $label }}</label>
    @endif

    <div class="auth-password">
        <input
            id="{{ $inputId }}"
            name="{{ $name }}"
            type="password"
            class="ui-input"
            required
            autocomplete="{{ $autocomplete ?? 'current-password' }}"
            @if(! empty($autofocus)) autofocus @endif
            @if($error) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        >
        <button type="button" class="auth-password-toggle" data-password-toggle="{{ $inputId }}"
            data-label-show="{{ __('translation.auth.show_password') }}" data-label-hide="{{ __('translation.auth.hide_password') }}"
            aria-label="{{ __('translation.auth.show_password') }}" title="{{ __('translation.auth.show_password') }}" aria-pressed="false">
            <span data-icon-show><x-ui.icon name="eye" /></span>
            <span data-icon-hide hidden><x-ui.icon name="eye-off" /></span>
        </button>
    </div>

    @if($error)
        <p class="ui-error" id="{{ $inputId }}-error"><x-ui.icon name="circle-alert" />{{ $error }}</p>
    @endif
</div>

@once
    <script @cspNonce>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-password-toggle]');
            if (!button) return;
            var input = document.getElementById(button.getAttribute('data-password-toggle'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            var label = button.getAttribute(show ? 'data-label-hide' : 'data-label-show');
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.querySelector('[data-icon-show]').hidden = show;
            button.querySelector('[data-icon-hide]').hidden = !show;
        });
    </script>
@endonce
