<?php

namespace Plugins\Gallery\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

#[Middleware('permission:gallery-settings')]
class SettingsController extends Controller
{
    protected const DEFAULTS = [
        'columns' => '3',
        'lightbox' => '1',
        'images_per_page' => '12',
        'pagination' => '1',
        'thumbnail_size' => 'medium',
        'gap' => '4',
        'aspect_ratio' => 'cover',
        'show_captions' => '1',
        'lazy_loading' => '1',
    ];

    public function edit(): View
    {
        $rawSettings = cms()->settings()->getByPrefix('gallery.');
        $settings = [];
        foreach ($rawSettings as $key => $value) {
            $settings["gallery.{$key}"] = $value;
        }

        return view('gallery-admin::settings', [
            'settings' => $settings,
            'defaults' => self::DEFAULTS,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'columns' => 'required|integer|min:2|max:6',
            'lightbox' => 'boolean',
            'images_per_page' => 'required|integer|min:0|max:200',
            'pagination' => 'boolean',
            'thumbnail_size' => 'required|in:small,medium,large',
            'gap' => 'required|in:2,4,6,8',
            'aspect_ratio' => 'required|in:cover,contain,auto',
            'show_captions' => 'boolean',
            'lazy_loading' => 'boolean',
        ]);

        cms()->settings()->set('gallery.columns', $request->input('columns'), 'integer');
        cms()->settings()->set('gallery.lightbox', $request->boolean('lightbox') ? '1' : '0', 'boolean');
        cms()->settings()->set('gallery.images_per_page', $request->input('images_per_page'), 'integer');
        cms()->settings()->set('gallery.pagination', $request->boolean('pagination') ? '1' : '0', 'boolean');
        cms()->settings()->set('gallery.thumbnail_size', $request->input('thumbnail_size'), 'string');
        cms()->settings()->set('gallery.gap', $request->input('gap'), 'integer');
        cms()->settings()->set('gallery.aspect_ratio', $request->input('aspect_ratio'), 'string');
        cms()->settings()->set('gallery.show_captions', $request->boolean('show_captions') ? '1' : '0', 'boolean');
        cms()->settings()->set('gallery.lazy_loading', $request->boolean('lazy_loading') ? '1' : '0', 'boolean');

        return redirect()
            ->route('backend.gallery.settings')
            ->with('success', __('gallery::gallery.settings_saved'));
    }
}
