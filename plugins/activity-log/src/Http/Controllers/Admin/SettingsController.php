<?php

namespace Plugins\ActivityLog\Http\Controllers\Admin;

use App\Contracts\SettingRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

#[Middleware('permission:activity-log-settings')]
class SettingsController extends Controller
{
    public function edit(): View
    {
        $settings = app(SettingRepository::class);

        return view('activity-log-admin::settings', [
            'retention_days' => $settings->get('activity_log.retention_days', '90'),
            'max_entries' => $settings->get('activity_log.max_entries', '10000'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'retention_days' => 'required|integer|min:0|max:3650',
            'max_entries' => 'required|integer|min:0|max:1000000',
        ]);

        $settings = app(SettingRepository::class);

        $settings->set('activity_log.retention_days', $request->input('retention_days'), 'integer', 'Delete activity log entries older than this many days. 0 = keep forever.');
        $settings->set('activity_log.max_entries', $request->input('max_entries'), 'integer', 'Maximum number of activity log entries to keep. 0 = unlimited.');

        return redirect()
            ->route('backend.activity-log.settings.edit')
            ->with('success', __('activity-log::activity.settings_saved'));
    }
}
