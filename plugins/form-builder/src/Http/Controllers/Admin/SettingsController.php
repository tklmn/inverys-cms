<?php

namespace Plugins\FormBuilder\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

#[Middleware('permission:form-settings')]
class SettingsController extends Controller
{
    protected const DEFAULTS = [
        'default_recipients' => '',
        'from_name' => '',
        'from_email' => '',
        'rate_limit' => '5',
    ];

    public function edit(): View
    {
        $rawSettings = cms()->settings()->getByPrefix('form_builder.');
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings["form_builder.{$key}"] = $value;
        }

        // Fallback when the fields here are empty: the sender actually in
        // effect. mail.from is already overridden by the mail plugin at boot
        // (if set there), otherwise the .env applies. Reading
        // setting('mailproviders.from_address') instead would hide the hint
        // when it is saved empty, even though the .env applies.
        $mailFallbacks = [
            'from_name' => (string) (config('mail.from.name') ?: config('app.name')),
            'from_email' => (string) config('mail.from.address', ''),
        ];

        return view('form-builder-admin::settings', [
            'settings' => $settings,
            'defaults' => self::DEFAULTS,
            'mailFallbacks' => $mailFallbacks,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'default_recipients' => 'nullable|string|max:500',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'rate_limit' => 'required|integer|min:1|max:100',
        ]);

        cms()->settings()->set('form_builder.default_recipients', $request->input('default_recipients') ?? '', 'string');
        cms()->settings()->set('form_builder.from_name', $request->input('from_name') ?? '', 'string');
        cms()->settings()->set('form_builder.from_email', $request->input('from_email') ?? '', 'string');
        cms()->settings()->set('form_builder.rate_limit', $request->input('rate_limit'), 'integer');

        return redirect()
            ->route('backend.forms.settings')
            ->with('success', __('form-builder::forms.settings_saved'));
    }
}
