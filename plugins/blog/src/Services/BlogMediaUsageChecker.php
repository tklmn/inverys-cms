<?php

namespace Plugins\Blog\Services;

use App\Contracts\MediaUsageChecker;
use Plugins\Blog\Models\Post;

class BlogMediaUsageChecker implements MediaUsageChecker
{
    // $media is a core media record (App\Models\Media); typed mixed to keep the
    // plugin free of a compile-time dependency on the core model class.
    public function getUsages(mixed $media): array
    {
        $usages = [];
        $path = $media->path;
        $storagePath = 'storage/'.$path;

        $posts = Post::where(function ($q) use ($path, $storagePath) {
            $q->where('featured_image', $path)
                ->orWhere('featured_image', $storagePath);
        })->get(['id', 'title']);

        foreach ($posts as $post) {
            $usages[] = [
                'type' => 'blog-post',
                'label' => __('blog::blog.post').': '.$post->title,
                'url' => route('backend.blog.edit', $post),
            ];
        }

        return $usages;
    }
}
