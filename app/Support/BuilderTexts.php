<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Labels of the page builder (JavaScript) in the UI language.
 *
 * One source for every place that embeds the builder — pages, posts,
 * theme regions. The basis is the translation.builder group; the remaining
 * texts are older JSON keys.
 */
final class BuilderTexts
{
    /**
     * @param  bool  $translating  Translation mode: "Reset" takes over the original
     * @return array<string, string>
     */
    public static function all(bool $translating = false): array
    {
        return array_merge(__('translation.builder'), [
            'reset_default' => $translating ? __('translation.locales.take_original') : __('translation.reset_to_default'),
            'page_builder' => __('Page Builder'),
            'html_code' => __('HTML Code'),
            'preview' => __('Preview'),
            'blocks_label' => __('Blocks'),
            'blocks_count' => __('block(s)'),
            'layout' => __('Layout'),
            'content_cat' => __('Content'),
            'interactive' => __('Interactive'),
            'empty_canvas' => __('Click a block type from the palette to add it'),
            'confirm_delete' => __('translation.pages.blocks.confirm_delete'),
            'cancel' => __('translation.cancel'),
            'delete' => __('translation.delete'),
            'autosaved' => __('translation.pages.blocks.autosaved'),
            'save_error' => __('translation.pages.save_error'),
            'loading' => __('Loading...'),
            'add_block' => __('Add block'),
            'add_section' => __('Add Section'),
            'add_tab' => __('Add Tab'),
            'section_title' => __('Section title'),
            'tab_title' => __('Tab title'),
            'media_library' => __('Media Library'),
            'search' => __('Search...'),
            'no_images' => __('No images found'),
            'no_files' => __('translation.media.no_files'),
            'library' => __('Library'),
            'enter_url_or_select' => __('Enter URL or select from library'),
        ]);
    }
}
