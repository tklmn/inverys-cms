<?php

namespace Plugins\ActivityLog\Http\Controllers\Admin;

use App\Helpers\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Plugins\ActivityLog\Models\ActivityLog;
use Plugins\ActivityLog\Services\ActivityLogger;

#[Middleware('permission:show-activity-log')]
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()->orderByDesc('created_at');

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by subject type
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Search on subject_label
        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where('subject_label', 'like', "%{$search}%");
        }

        $logs = $query->paginate(20)->withQueryString();

        // Get unique users and actions for filter dropdowns
        $users = cms()->users()->allForSelect();
        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $subjectTypes = ActivityLog::select('subject_type')->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type');

        return view('activity-log-admin::index', compact('logs', 'users', 'actions', 'subjectTypes'));
    }

    public function show(int $activityLog)
    {
        $log = ActivityLog::findOrFail($activityLog);

        return view('activity-log-admin::show', compact('log'));
    }

    public function destroy(int $activityLog)
    {
        abort_unless(auth()->user()->hasPermission('delete-activity-log'), 403);

        $log = ActivityLog::findOrFail($activityLog);
        $log->delete();

        return redirect()
            ->route('backend.activity-log.index')
            ->with('success', __('activity-log::activity.deleted'));
    }

    public function clear(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('delete-activity-log'), 403);

        $count = ActivityLog::query()->count();
        ActivityLog::query()->delete();

        // Record that the audit trail was cleared — logged after the wipe so this entry survives.
        ActivityLogger::log('cleared', null, ['deleted_count' => $count]);

        return redirect()
            ->route('backend.activity-log.index')
            ->with('success', __('activity-log::activity.cleared'));
    }
}
