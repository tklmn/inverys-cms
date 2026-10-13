<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\MediaImageProcessor;
use Illuminate\Console\Command;

/**
 * Backfill for images uploaded before responsive images existed: stores
 * width/height and generates the scaled copies for srcset. Safe to run
 * again — complete entries are skipped (unless --force), only the columns
 * width, height and variants are written, nothing else is touched.
 */
class MediaVariantsCommand extends Command
{
    protected $signature = 'media:variants
        {--force : Regenerate the scaled copies of every image, even complete ones}
        {--id=* : Only these media IDs}';

    protected $description = 'Store image sizes and generate the scaled copies (srcset) of media library images';

    public function handle(MediaImageProcessor $processor): int
    {
        $force = (bool) $this->option('force');
        $ids = array_filter(array_map('intval', (array) $this->option('id')));

        $query = Media::withTrashed()
            ->where('mime_type', 'like', 'image/%')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id');

        $counts = ['updated' => 0, 'skipped' => 0, 'failed' => 0];

        $this->components->info(MediaImageProcessor::writesWebp()
            ? 'Scaled copies are written as WebP.'
            : 'GD cannot write WebP here: scaled copies keep the format of the original.');

        $query->chunkById(100, function ($items) use ($processor, $force, &$counts) {
            foreach ($items as $media) {
                try {
                    $result = $processor->refresh($media, $force);
                } catch (\Throwable $e) {
                    $result = 'failed';
                    $this->components->warn("#{$media->id} {$media->path}: {$e->getMessage()}");
                }

                if ($result === 'failed' && $this->output->isVerbose()) {
                    $this->components->warn("#{$media->id} {$media->path}: file missing or not readable");
                }

                $counts[$result]++;
            }
        });

        $this->components->twoColumnDetail('Updated', (string) $counts['updated']);
        $this->components->twoColumnDetail('Skipped (complete, SVG or not an image)', (string) $counts['skipped']);
        $this->components->twoColumnDetail('Failed (file missing or unreadable)', (string) $counts['failed']);

        return self::SUCCESS;
    }
}
