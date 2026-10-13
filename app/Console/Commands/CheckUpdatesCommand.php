<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\Updates\UpdateChecker;
use Illuminate\Console\Command;

class CheckUpdatesCommand extends Command
{
    protected $signature = 'cms:check-updates';

    protected $description = 'Check the sources of the CMS, its plugins and themes for new releases';

    public function handle(UpdateChecker $checker, NotificationService $notifications): int
    {
        $checker->check();

        $available = array_filter($checker->status(), fn (array $item) => $item['available']);

        foreach ($available as $item) {
            // Only one notification per package and version
            $key = 'notification.update_notified.'.$item['key'];
            if (Setting::where('key', $key)->value('value') === $item['latest']) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => $item['latest']]);

            $notifications->create(
                'cms_update',
                __('translation.notifications.cms_update_available', [
                    'name' => $item['name'],
                    'version' => $item['latest'],
                    'installed' => $item['installed'],
                ]),
                null,
                ['key' => $item['key'], 'version' => $item['latest'], 'installed' => $item['installed']]
            );
        }

        $this->info(count($available).' update(s) available.');

        return self::SUCCESS;
    }
}
