<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->insertOrIgnore([
            [
                'key' => 'language',
                'value' => 'en',
                'type' => 'string',
                'description' => 'Default language',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'theme',
                'value' => 'light',
                'type' => 'string',
                'description' => 'Default theme',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'timezone',
                'value' => 'Europe/Berlin',
                'type' => 'string',
                'description' => 'Default timezone',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'date_format',
                'value' => 'd.m.Y',
                'type' => 'string',
                'description' => 'Default date format',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'notification_email',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Default notification email setting',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'favicon',
                'value' => 'images/favicon.svg',
                'type' => 'string',
                'description' => 'Favicon path (relative to public)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_name',
                'value' => 'Inverys CMS',
                'type' => 'string',
                'description' => 'Site name displayed in titles and navigation',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.two_factor_enabled',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Enable two-factor authentication for all users',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'security.two_factor_code_lifetime',
                'value' => '10',
                'type' => 'string',
                'description' => 'Email verification code lifetime in minutes',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.include_pages',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Include pages in search results',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.pages.fields.title',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Search in page titles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.pages.fields.content',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Search in page content',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.pages.fields.meta',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Search in page meta descriptions',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.min_length',
                'value' => '2',
                'type' => 'string',
                'description' => 'Minimum search query length',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.per_page',
                'value' => '10',
                'type' => 'string',
                'description' => 'Search results per page',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'search.guest_access',
                'value' => 'on',
                'type' => 'string',
                'description' => 'Allow guest access to search',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed theme defaults from theme.json files
        $this->seedThemeDefaults();
    }

    private function seedThemeDefaults(): void
    {
        $themesPath = config('cms.themes_path', base_path('themes'));

        if (! is_dir($themesPath)) {
            return;
        }

        foreach (File::directories($themesPath) as $dir) {
            $manifest = $dir.'/theme.json';
            if (! File::exists($manifest)) {
                continue;
            }

            $theme = json_decode(File::get($manifest), true);
            if (! $theme || ! isset($theme['slug'])) {
                continue;
            }

            $slug = $theme['slug'];

            // Seed settings defaults
            foreach ($theme['settings'] ?? [] as $key => $schema) {
                $default = $schema['default'] ?? '';
                if ($default === '' || $default === false) {
                    continue;
                }

                DB::table('settings')->insertOrIgnore([
                    'key' => "theme.{$slug}.{$key}",
                    'value' => is_bool($default) ? ($default ? '1' : '0') : (string) $default,
                    'type' => 'theme_setting',
                    'description' => ($schema['label'] ?? $key)." ({$slug} theme)",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Seed region defaults
            foreach ($theme['regions'] ?? [] as $key => $schema) {
                if (empty($schema['default'])) {
                    continue;
                }

                DB::table('settings')->insertOrIgnore([
                    'key' => "theme.{$slug}.region.{$key}",
                    'value' => json_encode($schema['default']),
                    'type' => 'theme_region',
                    'description' => ($schema['label'] ?? $key)." ({$slug} theme)",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
