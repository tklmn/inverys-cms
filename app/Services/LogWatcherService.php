<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\File;

class LogWatcherService
{
    public function check(): array
    {
        $logPath = storage_path('logs');
        $files = File::files($logPath);

        $current = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'log') {
                continue;
            }
            $current[$file->getFilename()] = $file->getMTime();
        }

        $snapshot = $this->getSnapshot();

        $new = [];
        $modified = [];

        foreach ($current as $name => $mtime) {
            if (! isset($snapshot[$name])) {
                $new[] = $name;
            } elseif ($snapshot[$name] < $mtime) {
                $modified[] = $name;
            }
        }

        $this->saveSnapshot($current);

        return ['new' => $new, 'modified' => $modified];
    }

    private function getSnapshot(): array
    {
        try {
            $setting = Setting::where('key', 'notification.log_snapshot')->first();

            return $setting ? json_decode($setting->value, true) ?? [] : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function saveSnapshot(array $snapshot): void
    {
        Setting::updateOrCreate(
            ['key' => 'notification.log_snapshot'],
            ['value' => json_encode($snapshot)]
        );
    }
}
