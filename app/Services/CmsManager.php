<?php

namespace App\Services;

use App\Contracts\CmsUserProvider;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\MediaManager;
use App\Contracts\PageManager;
use App\Contracts\PermissionManager;
use App\Contracts\RouteRegistrar;
use App\Contracts\SettingRepository;
use Illuminate\Contracts\Foundation\Application;

class CmsManager
{
    public function __construct(protected Application $app) {}

    public function settings(): SettingRepository
    {
        return $this->app->make(SettingRepository::class);
    }

    public function permissions(): PermissionManager
    {
        return $this->app->make(PermissionManager::class);
    }

    public function pages(): PageManager
    {
        return $this->app->make(PageManager::class);
    }

    public function media(): MediaManager
    {
        return $this->app->make(MediaManager::class);
    }

    public function users(): CmsUserProvider
    {
        return $this->app->make(CmsUserProvider::class);
    }

    public function routes(): RouteRegistrar
    {
        return $this->app->make(RouteRegistrar::class);
    }

    public function extensions(): ExtensionRegistrar
    {
        return $this->app->make(ExtensionRegistrar::class);
    }

    public function themes(): ThemeManager
    {
        return $this->app->make(ThemeManager::class);
    }

    public function plugins(): PluginManager
    {
        return $this->app->make(PluginManager::class);
    }

    public function version(): string
    {
        return config('cms.version', '0.0.0');
    }
}
