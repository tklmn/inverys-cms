<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Images of the demo pages in the media library.
 *
 * One list for the PageSeeder and for cms:demo-refresh — so new
 * illustrations also reach existing installations.
 */
final class DemoMedia
{
    /** File → [name, alt text] */
    public const IMAGES = [
        'hero-abstract.svg' => ['Hero Abstract Background', 'Abstract hero background with geometric shapes'],
        'about-team.svg' => ['About Team', 'Abstract team and collaboration illustration'],
        'team-lead.svg' => ['Project Lead', 'Project lead portrait'],
        'team-design.svg' => ['Design Lead', 'Design lead portrait'],
        'team-dev.svg' => ['Developer', 'Backend developer portrait'],
        'services-web.svg' => ['Web Development', 'Web development service illustration'],
        'services-consulting.svg' => ['Consulting', 'Technology consulting illustration'],
        'features-overview.svg' => ['Features Overview', 'Platform features overview illustration'],
        'contact-hero.svg' => ['Contact', 'Contact and communication illustration'],
        'map-placeholder.svg' => ['Map', 'Map placeholder illustration'],
        'docs-hero.svg' => ['Documentation', 'Documentation illustration'],
        'feature-languages.svg' => ['Multilingual', 'Two language versions of the same page'],
        'feature-media.svg' => ['Media Library', 'Image grid of the media library'],
        'feature-security.svg' => ['Security', 'Shield with a check mark'],
        'avatar-1.svg' => ['Avatar Sarah', 'Portrait illustration'],
        'avatar-2.svg' => ['Avatar Marcus', 'Portrait illustration'],
        'avatar-3.svg' => ['Avatar Emily', 'Portrait illustration'],
        'logo-1.svg' => ['Logo Circle', 'Company logo'],
        'logo-2.svg' => ['Logo Peak', 'Company logo'],
        'logo-3.svg' => ['Logo Grid', 'Company logo'],
        'logo-4.svg' => ['Logo Orbit', 'Company logo'],
        'logo-5.svg' => ['Logo Arc', 'Company logo'],
    ];

    /**
     * Write the files (existing ones are renewed) and create missing entries
     * in the media library.
     *
     * @return int number of files written
     */
    public static function ensure(): int
    {
        $storage = Storage::disk('public');
        $storage->makeDirectory('media/demo');
        $admin = User::first();

        foreach (self::IMAGES as $file => [$name, $alt]) {
            $path = 'media/demo/'.$file;
            $storage->put($path, DemoIllustrations::svg($file));

            Media::firstOrCreate(['path' => $path], [
                'name' => $name,
                'file_name' => $file,
                'thumbnail_path' => $path,
                'mime_type' => 'image/svg+xml',
                'size' => $storage->size($path),
                'alt_text' => $alt,
                'description' => $alt,
                'user_id' => $admin?->id,
            ]);
        }

        return count(self::IMAGES);
    }
}
