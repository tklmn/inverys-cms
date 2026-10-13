<?php

namespace App\Services\Cms;

use App\Contracts\MediaUsageChecker;
use App\Models\Media;
use App\Models\Page;

class PageMediaUsageChecker implements MediaUsageChecker
{
    public function getUsages(Media $media): array
    {
        $usages = [];
        $path = $media->path;
        $storagePath = 'storage/'.$path;

        // Check header_image and og_image columns
        $pages = Page::where(function ($q) use ($path, $storagePath) {
            $q->where('header_image', $path)
                ->orWhere('header_image', $storagePath)
                ->orWhere('og_image', $path)
                ->orWhere('og_image', $storagePath);
        })->get(['id', 'title', 'header_image', 'og_image']);

        foreach ($pages as $page) {
            $fields = [];
            if (in_array($page->header_image, [$path, $storagePath], true)) {
                $fields[] = __('translation.media.usage_header_image');
            }
            if (in_array($page->og_image, [$path, $storagePath], true)) {
                $fields[] = __('translation.media.usage_og_image');
            }

            $usages[] = [
                'type' => 'page',
                'label' => __('translation.pages.page').': '.$page->title.' ('.implode(', ', $fields).')',
                'url' => route('backend.pages.edit', $page),
            ];
        }

        // Check page builder blocks JSON for media path references
        $pagesWithBlocks = Page::where('content_type', 'blocks')
            ->whereNotNull('blocks')
            ->where(function ($q) use ($path, $storagePath) {
                $q->where('blocks', 'like', '%'.addcslashes($path, '%_').'%')
                    ->orWhere('blocks', 'like', '%'.addcslashes($storagePath, '%_').'%');
            })
            ->get(['id', 'title']);

        foreach ($pagesWithBlocks as $page) {
            // Avoid duplicate if already listed via header/og
            if ($pages->contains('id', $page->id)) {
                continue;
            }

            $usages[] = [
                'type' => 'page',
                'label' => __('translation.pages.page').': '.$page->title.' ('.__('translation.media.usage_page_builder').')',
                'url' => route('backend.pages.edit', $page),
            ];
        }

        return $usages;
    }
}
