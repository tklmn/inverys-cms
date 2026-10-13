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

    public function render(?array $blocks): string
    {
        if (empty($blocks)) {
            return '';
        }

        $html = '';
        foreach ($blocks as $block) {
            // Skip broken entries (e.g. from an old region) instead of
            // aborting the whole page with an error
            if (is_array($block)) {
                $html .= $this->renderBlock($block);
            }
        }

        return $html;
    }

    protected function renderBlock(array $block): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : 'text';
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];

        $childrenHtml = '';
        if (! empty($children)) {
            $childrenHtml = $this->render($children);
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
            'id' => $block['id'] ?? '',
        ])->render();

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
