<?php

namespace App\Services;

use App\Models\BlockType;
use App\Support\BlockDesign;
use App\Support\EditorCanvas;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class BlockRenderer
{
    protected static array $pluginViewCache = [];

    /** Languages of the enclosing blocks (settings.lang), innermost last */
    protected array $langStack = [];

    /** The first block with an image (document order) has not been found yet */
    protected bool $priorityPending = false;

    /** @var array<string, callable(array, array): bool> Plugin blocks that show images */
    protected static array $imageDetectors = [];

    /**
     * @param  array|null  $parent  Enclosing block — its view settings reach the
     *                              children as $parentBlock (e.g. the accordion's heading level)
     * @param  bool|null  $prioritizeFirstImage  Top level only: the first block that shows an
     *                                           image gets $imagePriority = true (eager,
     *                                           fetchpriority="high" — it is most likely the
     *                                           largest image above the fold). Default: yes for
     *                                           a page; false when something above it (a header
     *                                           image) already has the priority, or for regions.
     */
    public function render(?array $blocks, ?array $parent = null, ?bool $prioritizeFirstImage = null): string
    {
        if (empty($blocks)) {
            return '';
        }

        $topLevel = $parent === null;
        $previous = $this->priorityPending;
        if ($topLevel) {
            $this->priorityPending = $prioritizeFirstImage ?? true;
        }

        try {
            $html = '';
            foreach ($blocks as $block) {
                // Skip broken entries (e.g. from an old region) instead of
                // aborting the whole page with an error
                if (is_array($block)) {
                    $html .= $this->renderBlock($block, $parent);
                }
            }
        } finally {
            if ($topLevel) {
                $this->priorityPending = $previous;
            }
        }

        return $html;
    }

    /**
     * Let a plugin block take part in the "first image of the page" rule.
     *
     *   BlockRenderer::registerImageBlock('gallery', fn (array $data, array $settings) => ! empty($data['album_id']));
     *
     * @param  callable(array, array): bool  $detector
     */
    public static function registerImageBlock(string $type, callable $detector): void
    {
        static::$imageDetectors[$type] = $detector;
    }

    /** Whether a block shows an image of its own (background included, children not) */
    public static function showsImage(string $type, array $data, array $settings): bool
    {
        if (trim((string) ($settings['bg_image'] ?? '')) !== '') {
            return true;
        }

        if (isset(static::$imageDetectors[$type])) {
            return (bool) (static::$imageDetectors[$type])($data, $settings);
        }

        $any = fn (mixed $items) => is_array($items) && collect($items)->contains(fn ($item) => is_array($item) && filled($item['image'] ?? null));

        return match ($type) {
            'image' => filled($data['src'] ?? null),
            'hero' => filled($data['image'] ?? null) && ($settings['layout'] ?? 'split') !== 'centered',
            'card' => filled($data['image'] ?? null),
            'logos' => $any($data['items'] ?? null),
            'slider' => $any($data['slides'] ?? null),
            default => false,
        };
    }

    protected function renderBlock(array $block, ?array $parent = null): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : 'text';
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];

        // Language of the block (e.g. an English quote on a German page):
        // only marked where it differs from the surrounding language
        $lang = BlockDesign::lang($settings['lang'] ?? null);
        $surrounding = end($this->langStack) ?: str_replace('_', '-', app()->getLocale());

        // Decided before the children are rendered: a container's background
        // comes before the images inside it
        $imagePriority = false;
        if ($this->priorityPending && static::showsImage($type, $data, $settings)) {
            $imagePriority = true;
            $this->priorityPending = false;
        }

        $childrenHtml = '';
        if (! empty($children)) {
            if ($lang !== '') {
                $this->langStack[] = $lang;
            }
            try {
                $childrenHtml = $this->render($children, $block);
            } finally {
                if ($lang !== '') {
                    array_pop($this->langStack);
                }
            }
        }

        // Theme can override block views, otherwise use core
        $themeView = "theme::blocks.{$type}";
        $viewName = View::exists($themeView) ? $themeView : "frontend.blocks.{$type}";

        // Fallback: check for plugin-provided block view
        if (! View::exists($viewName)) {
            $viewName = $this->resolvePluginBlockView($type);
        }

        if (! $viewName || ! View::exists($viewName)) {
            return $childrenHtml;
        }

        $html = View::make($viewName, [
            'block' => $block,
            'data' => $data,
            'settings' => $settings,
            'children' => $childrenHtml,
            'rawChildren' => $children,
            'parentBlock' => $parent,
            'id' => $block['id'] ?? '',
            // First block with an image on the page: load it eagerly with high priority
            'imagePriority' => $imagePriority,
        ])->render();

        if ($lang !== '' && strcasecmp($lang, $surrounding) !== 0) {
            $html = BlockDesign::attachAttributes($html, ' lang="'.e($lang).'"');
        }

        // "Desktop only / mobile only" (Advanced) — don't wrap columns, or the grid breaks
        $visibility = BlockDesign::visibility($settings['visibility'] ?? null);
        if ($visibility !== '' && $type !== 'column') {
            $html = '<div class="'.$visibility.'">'.$html.'</div>';
        }

        // Editor preview only: block selectable by click
        return EditorCanvas::annotate($html, (string) ($block['id'] ?? ''), $type);
    }

    protected function resolvePluginBlockView(string $type): ?string
    {
        if (array_key_exists($type, static::$pluginViewCache)) {
            return static::$pluginViewCache[$type];
        }

        if (! Schema::hasTable('block_types')) {
            return static::$pluginViewCache[$type] = null;
        }

        $blockType = BlockType::where('slug', $type)->whereNotNull('plugin_slug')->first();

        if (! $blockType) {
            return static::$pluginViewCache[$type] = null;
        }

        $pluginView = "{$blockType->plugin_slug}::blocks.{$type}";

        return static::$pluginViewCache[$type] = View::exists($pluginView) ? $pluginView : null;
    }
}
