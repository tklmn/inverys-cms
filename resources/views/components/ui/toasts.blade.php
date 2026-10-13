{{--
    Short messages as toasts.

    Success and status messages are fleeting information — once read they
    have no value. As a box in the page flow they push the content down
    and stay until the page is reloaded. As a toast at the top right
    they disappear on their own.

    Validation errors explicitly do NOT belong here: they need to be read
    at leisure, so they stay in the form via x-ui.flash.
--}}
@php
    $toasts = collect([
        'success' => 'positive',
        'status' => 'positive',
        'warning' => 'caution',
        'error' => 'critical',
    ])->filter(fn ($tone, $key) => filled(session($key)))
      ->map(function ($tone, $key) {
          $text = (string) session($key);
          // Translate identifiers like "profile-updated" (Breeze style) into sentences
          if (preg_match('/^[a-z]+(?:-[a-z]+)+$/', $text)) {
              $translationKey = 'translation.flash.'.$text;
              $text = __($translationKey) !== $translationKey ? __($translationKey) : null;
          }

          return ['tone' => $tone, 'text' => $text];
      })
      ->filter(fn ($toast) => filled($toast['text']));
@endphp

<div class="ui-toasts" data-toasts aria-live="polite" aria-atomic="false">
    @foreach($toasts as $toast)
        <div class="ui-toast ui-toast-{{ $toast['tone'] }}" data-toast role="{{ $toast['tone'] === 'critical' ? 'alert' : 'status' }}">
            <span class="ui-toast-icon">
                <x-ui.icon :name="match($toast['tone']) {
                    'positive' => 'circle-check',
                    'caution' => 'triangle-alert',
                    'critical' => 'circle-alert',
                    default => 'info',
                }" />
            </span>

            <p class="ui-toast-text">{{ $toast['text'] }}</p>

            <button type="button" class="ui-toast-close" data-toast-close aria-label="{{ __('translation.close') }}">
                <x-ui.icon name="x" />
            </button>
        </div>
    @endforeach
</div>
