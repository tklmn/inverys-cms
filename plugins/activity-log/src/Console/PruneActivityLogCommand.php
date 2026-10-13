<?php

namespace Plugins\ActivityLog\Console;

use App\Contracts\SettingRepository;
use Illuminate\Console\Command;
use Plugins\ActivityLog\Models\ActivityLog;

class PruneActivityLogCommand extends Command
{
    protected $signature = 'activity-log:prune';

    protected $description = 'Prune old activity log entries based on retention settings';

    public function handle(): int
    {
        $settings = app(SettingRepository::class);
        $retentionDays = (int) $settings->get('activity_log.retention_days', 90);
        $maxEntries = (int) $settings->get('activity_log.max_entries', 10000);
        $totalDeleted = 0;

        // Delete entries older than retention_days (skip if 0 = keep forever)
        if ($retentionDays > 0) {
            $cutoff = now()->subDays($retentionDays);
            $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();
            $totalDeleted += $deleted;
        }

        // If total exceeds max_entries, delete oldest excess entries (skip if 0 = unlimited)
        if ($maxEntries > 0) {
            $totalCount = ActivityLog::count();
            if ($totalCount > $maxEntries) {
                // Delete in a single query using subquery
                $cutoffId = ActivityLog::orderByDesc('created_at')
                    ->skip($maxEntries)
                    ->value('id');
                if ($cutoffId) {
                    $deleted = ActivityLog::where('id', '<=', $cutoffId)->delete();
                    $totalDeleted += $deleted;
                }
            }
        }

        $this->info(__('activity-log::activity.prune_complete', ['count' => $totalDeleted]));

        return self::SUCCESS;
    }
}
