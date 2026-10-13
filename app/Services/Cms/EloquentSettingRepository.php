<?php

namespace App\Services\Cms;

use App\Contracts\SettingRepository;
use App\Models\Setting;
use App\Support\TranslatableSettings;

class EloquentSettingRepository implements SettingRepository
{
    protected static array $cache = [];

    protected static bool $loaded = false;

    /**
     * Bulk-load all settings into cache with a single query.
     * Called once per request on first access.
     */
    protected function loadAll(): void
    {
        if (static::$loaded) {
            return;
        }

        $settings = Setting::whereNull('deleted_at')->get();

        foreach ($settings as $setting) {
            static::$cache[$setting->key] = $setting->value;
        }

        static::$loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->loadAll();

        if (array_key_exists($key, static::$cache)) {
            return $this->localized($key, static::$cache[$key] ?? $default);
        }

        return $default;
    }

    /**
     * Translatable settings (site name, banner texts …) in the frontend in
     * the visitor's language. Unchanged without the multilingual plugin.
     */
    protected function localized(string $key, mixed $value): mixed
    {
        return app(TranslatableSettings::class)->apply($key, $value);
    }

    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $data = ['value' => $value ?? '', 'type' => $type];
        if ($description !== null) {
            $data['description'] = $description;
        }

        Setting::updateOrCreate(['key' => $key], $data);

        // Update the in-memory cache entry directly (no need to reload everything)
        static::$cache[$key] = $value ?? '';
    }

    public function has(string $key): bool
    {
        $this->loadAll();

        return array_key_exists($key, static::$cache);
    }

    public function getByPrefix(string $prefix): array
    {
        $this->loadAll();

        $result = [];
        foreach (static::$cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $shortKey = substr($key, strlen($prefix));
                $result[$shortKey] = $this->localized($key, $value);
            }
        }

        return $result;
    }

    public function delete(string $key): bool
    {
        $deleted = Setting::where('key', $key)->delete() > 0;

        // Bust the full cache so next access reloads clean state
        static::$loaded = false;
        static::$cache = [];

        return $deleted;
    }

    public static function clearCache(): void
    {
        static::$loaded = false;
        static::$cache = [];
    }
}
