<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class LaravelUpdateCheckerService
{
    public function check(): ?array
    {
        try {
            $installed = $this->getInstalledVersion();
            if (! $installed) {
                return null;
            }

            $latest = $this->getLatestVersion();
            if (! $latest) {
                return null;
            }

            if (version_compare($latest, $installed, '<=')) {
                return null;
            }

            $notified = Setting::where('key', 'notification.laravel_latest_notified')->value('value');
            if ($notified === $latest) {
                return null;
            }

            Setting::updateOrCreate(
                ['key' => 'notification.laravel_latest_notified'],
                ['value' => $latest]
            );

            return [
                'installed' => $installed,
                'latest' => $latest,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getInstalledVersion(): ?string
    {
        $lockPath = base_path('composer.lock');
        if (! file_exists($lockPath)) {
            return null;
        }

        $lock = json_decode(file_get_contents($lockPath), true);
        foreach ($lock['packages'] ?? [] as $package) {
            if ($package['name'] === 'laravel/framework') {
                return ltrim($package['version'] ?? '', 'v');
            }
        }

        return null;
    }

    private function getLatestVersion(): ?string
    {
        $response = Http::timeout(10)->get('https://repo.packagist.org/p2/laravel/framework.json');

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        $versions = $data['packages']['laravel/framework'] ?? [];

        $latest = null;
        foreach ($versions as $release) {
            $v = ltrim($release['version'] ?? '', 'v');
            if (! preg_match('/^\d+\.\d+\.\d+$/', $v)) {
                continue;
            }
            if ($latest === null || version_compare($v, $latest, '>')) {
                $latest = $v;
            }
        }

        return $latest;
    }
}
