<?php

namespace App\Console\Commands;

use App\Contracts\ContentTranslator;
use App\Models\Media;
use App\Models\Page;
use App\Services\BlockRegistry;
use App\Services\PluginManager;
use Database\Seeders\Demo\DemoIllustrations;
use Database\Seeders\Demo\DemoMedia;
use Database\Seeders\Demo\DemoPages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the demo content of an existing installation up to date with the
 * current version: blocks of the demo pages, new child pages (e.g. the
 * individual features), illustrations, the demo content of plugins
 * (refreshDemoContent(), e.g. the blog posts) and the language packs' versions.
 *
 * A revision is created for each page first — your own changes can be
 * restored via "Revisions".
 */
class DemoRefreshCommand extends Command
{
    protected $signature = 'cms:demo-refresh {--force : Do not ask for confirmation}';

    protected $description = 'Replace the demo pages and images with the current demo content (creates revisions first)';

    public function handle(ContentTranslator $translator): int
    {
        $pages = Page::whereIn('slug', array_keys(DemoPages::PAGES))->get();

        if ($pages->isEmpty()) {
            $this->warn('No demo pages found.');

            return self::SUCCESS;
        }

        $this->line('Pages: '.$pages->pluck('slug')->implode(', '));

        if (! $this->option('force') && ! $this->confirm('Replace the blocks of these pages and the demo content of plugins (such as the demo blog posts)? Their translations are removed; revisions keep the current state.')) {
            return self::FAILURE;
        }

        foreach ($pages as $page) {
            $page->createRevision('Demo content refresh');
            $page->forceFill(['content_type' => 'blocks', 'blocks' => DemoPages::for($page->slug)])->saveQuietly();

            // Translated blocks no longer match the new structure
            foreach ($translator->locales($page) as $locale) {
                $translator->save($page, $locale, ['blocks' => null]);
            }
        }

        // New child pages that do not exist in this installation yet
        $created = DemoPages::createMissingChildren();

        // Images of the demo pages (creates new ones) and the remaining demo graphics (blog …)
        $images = DemoMedia::ensure();
        $storage = Storage::disk('public');
        foreach (Media::where('path', 'like', 'media/demo/%')->get() as $media) {
            if (! isset(DemoMedia::IMAGES[$media->file_name]) && DemoIllustrations::has($media->file_name)) {
                $storage->put($media->path, DemoIllustrations::svg($media->file_name));
                $media->forceFill(['size' => $storage->size($media->path)])->saveQuietly();
                $images++;
            }
        }

        // Plugins refresh their own demo content (e.g. the demo blog posts)
        $plugins = 0;
        foreach (app(PluginManager::class)->getLoadedProviders() as $provider) {
            if (method_exists($provider, 'refreshDemoContent')) {
                $plugins += (int) $provider->refreshDemoContent();
            }
        }

        // Language packs lay their version of the new texts on top
        foreach (app(PluginManager::class)->getLoadedProviders() as $provider) {
            if (method_exists($provider, 'seedDemoTranslations')) {
                $provider->seedDemoTranslations();
            }
        }

        BlockRegistry::clearCache();

        $this->info("Refreshed {$pages->count()} pages and {$plugins} plugin entries, created {$created} pages, wrote {$images} images.");

        return self::SUCCESS;
    }
}
