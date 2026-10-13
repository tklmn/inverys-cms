<?php

namespace App\Console\Commands;

use App\Services\LaravelUpdateCheckerService;
use App\Services\LogWatcherService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckNotificationsCommand extends Command
{
    protected $signature = 'cms:check-notifications';

    protected $description = 'Check for new log files and Laravel updates, create notifications';

    public function handle(
        NotificationService $notifications,
        LogWatcherService $logWatcher,
        LaravelUpdateCheckerService $updateChecker
    ): int {
        // Check log files
        $logChanges = $logWatcher->check();

        foreach ($logChanges['new'] as $filename) {
            $notifications->create(
                'log_change',
                __('translation.notifications.log_new', ['filename' => $filename]),
                null,
                ['filename' => $filename, 'change' => 'new']
            );
        }

        foreach ($logChanges['modified'] as $filename) {
            $notifications->create(
                'log_change',
                __('translation.notifications.log_modified', ['filename' => $filename]),
                null,
                ['filename' => $filename, 'change' => 'modified']
            );
        }

        // Check Laravel updates
        $update = $updateChecker->check();
        if ($update) {
            $notifications->create(
                'laravel_update',
                __('translation.notifications.laravel_update_available', [
                    'version' => $update['latest'],
                    'installed' => $update['installed'],
                ]),
                null,
                $update
            );
        }

        // Prune old notifications
        $pruned = $notifications->prune();

        $logCount = count($logChanges['new']) + count($logChanges['modified']);
        $this->info("Checked: {$logCount} log changes, ".($update ? '1 update' : 'no updates').", pruned {$pruned} old.");

        return self::SUCCESS;
    }
}
