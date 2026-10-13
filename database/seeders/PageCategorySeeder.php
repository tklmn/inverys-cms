<?php

namespace Database\Seeders;

use App\Models\PageCategory;
use Illuminate\Database\Seeder;

class PageCategorySeeder extends Seeder
{
    public function run(): void
    {
        if (PageCategory::count() > 0) {
            $this->command->info('Page categories already exist. Skipping.');

            return;
        }

        $categories = [
            ['name' => 'General',    'slug' => 'general',    'description' => 'General content pages'],
            ['name' => 'Blog',       'slug' => 'blog',       'description' => 'Blog posts and articles'],
            ['name' => 'Legal',      'slug' => 'legal',      'description' => 'Legal and compliance pages'],
            ['name' => 'Landing',    'slug' => 'landing',    'description' => 'Marketing landing pages'],
            ['name' => 'Support',    'slug' => 'support',    'description' => 'Help and support pages'],
        ];

        foreach ($categories as $category) {
            PageCategory::create($category);
        }

        $this->command->info('Page categories seeded successfully!');
    }
}
