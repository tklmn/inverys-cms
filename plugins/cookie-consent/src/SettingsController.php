<?php

namespace Plugins\CookieConsent;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

// Like the menu item: manage-plugins only (services hold scripts for every page)
#[Middleware('permission:manage-plugins')]
class SettingsController extends Controller
{
    public function edit(): View
    {
        $cc = cms()->settings()->getByPrefix('cc.');

        $pages = cms()->pages()->activeForSelect();

        return view('cookie-consent::settings', [
            'cc' => $cc,
            'pages' => $pages,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'banner_text' => 'nullable|string|max:1000',
            'privacy_page_slug' => 'nullable|string|max:255',
            'imprint_page_slug' => 'nullable|string|max:255',
            'position' => 'required|in:bottom,modal,corner',
            'bg_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'btn_bg_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'btn_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cookie_lifetime' => 'required|integer|min:1|max:3650',
            'reopen_position' => 'required|in:left,right',
        ]);

        $s = cms()->settings();
        // boolean() instead of has(): the switch sends a field even when "off"
        // (hidden field with 0), so has() would always be true.
        $s->set('cc.enabled', $request->boolean('enabled') ? '1' : '0', 'plugin_setting');
        $s->set('cc.banner_text', $request->input('banner_text', ''), 'plugin_setting');
        $s->set('cc.privacy_page_slug', $request->input('privacy_page_slug', ''), 'plugin_setting');
        $s->set('cc.imprint_page_slug', $request->input('imprint_page_slug', ''), 'plugin_setting');
        $s->set('cc.position', $request->input('position'), 'plugin_setting');
        $s->set('cc.bg_color', $request->input('bg_color'), 'plugin_setting');
        $s->set('cc.text_color', $request->input('text_color'), 'plugin_setting');
        $s->set('cc.btn_bg_color', $request->input('btn_bg_color'), 'plugin_setting');
        $s->set('cc.btn_text_color', $request->input('btn_text_color'), 'plugin_setting');
        $s->set('cc.cookie_lifetime', $request->input('cookie_lifetime'), 'plugin_setting');
        $s->set('cc.show_reopen_btn', $request->boolean('show_reopen_btn') ? '1' : '0', 'plugin_setting');
        $s->set('cc.reopen_position', $request->input('reopen_position', 'left'), 'plugin_setting');

        return redirect()->route('backend.cookie-consent.settings')
            ->with('success', __('cookie-consent::cc.settings_saved'));
    }
}
