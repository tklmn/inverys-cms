<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\User;
use Database\Seeders\Demo\DemoIllustrations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        if (Media::count() > 0) {
            $this->command?->info('Media already exist. Skipping.');

            return;
        }

        $admin = User::first();
        $storage = Storage::disk('public');
        $storage->makeDirectory('media/demo');

        $images = [
            ['file' => 'hero-abstract.svg', 'name' => 'Hero Abstract Background', 'alt' => 'Abstract hero background with geometric shapes'],
            ['file' => 'about-team.svg', 'name' => 'About Team', 'alt' => 'Abstract team and collaboration illustration'],
            ['file' => 'team-lead.svg', 'name' => 'Project Lead', 'alt' => 'Project lead portrait'],
            ['file' => 'team-design.svg', 'name' => 'Design Lead', 'alt' => 'Design lead portrait'],
            ['file' => 'team-dev.svg', 'name' => 'Developer', 'alt' => 'Backend developer portrait'],
            ['file' => 'services-web.svg', 'name' => 'Web Development', 'alt' => 'Web development service illustration'],
            ['file' => 'services-consulting.svg', 'name' => 'Consulting', 'alt' => 'Technology consulting illustration'],
            ['file' => 'features-overview.svg', 'name' => 'Features Overview', 'alt' => 'Platform features overview illustration'],
            ['file' => 'contact-hero.svg', 'name' => 'Contact', 'alt' => 'Contact and communication illustration'],
            ['file' => 'map-placeholder.svg', 'name' => 'Map', 'alt' => 'Map placeholder illustration'],
            ['file' => 'docs-hero.svg', 'name' => 'Documentation', 'alt' => 'Documentation illustration'],
            ['file' => 'blog-getting-started.svg', 'name' => 'Getting Started', 'alt' => 'Getting started guide illustration'],
            ['file' => 'blog-page-builder.svg', 'name' => 'Page Builder', 'alt' => 'Visual page builder illustration'],
            ['file' => 'blog-themes.svg', 'name' => 'Theme System', 'alt' => 'Theme customization illustration'],
            ['file' => 'blog-tips.svg', 'name' => 'Tips and Tricks', 'alt' => 'Productivity tips illustration'],
            ['file' => 'blog-plugins.svg', 'name' => 'Plugin Development', 'alt' => 'Plugin architecture illustration'],
            ['file' => 'blog-api.svg', 'name' => 'API Integration', 'alt' => 'REST API integration illustration'],
        ];

        foreach ($images as $img) {
            $path = 'media/demo/'.$img['file'];

            // Textfreie Illustration (Database\Seeders\Demo\DemoIllustrations)
            $storage->put($path, DemoIllustrations::svg($img['file']));

            Media::create([
                'name' => $img['name'],
                'file_name' => $img['file'],
                'path' => $path,
                'thumbnail_path' => $path,
                'mime_type' => 'image/svg+xml',
                'size' => $storage->size($path),
                'alt_text' => $img['alt'],
                'description' => $img['alt'],
                'user_id' => $admin?->id,
            ]);
        }

        $this->command?->info('Demo media seeded: '.Media::count().' files');
    }
}
