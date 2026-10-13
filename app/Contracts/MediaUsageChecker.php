<?php

namespace App\Contracts;

use App\Models\Media;

interface MediaUsageChecker
{
    /**
     * Return an array of usages for the given media item.
     *
     * Each entry: ['type' => 'page', 'label' => 'About Us', 'url' => '/admin/pages/5/edit']
     *
     * @return array<int, array{type: string, label: string, url: string|null}>
     */
    public function getUsages(Media $media): array;
}
