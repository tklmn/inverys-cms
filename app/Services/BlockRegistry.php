<?php

namespace App\Services;

use App\Models\BlockType;

class BlockRegistry
{
    protected array $types = [];

    public function boot(): void
    {
        $blockTypes = cache()->remember('block_registry.types', 3600, function () {
            $this->ensureCoreTypes();

            return BlockType::all();
        });

        foreach ($blockTypes as $blockType) {
            $this->types[$blockType->slug] = [
                'name' => $blockType->name,
                'category' => $blockType->category,
                'icon' => $blockType->icon,
                'is_core' => $blockType->is_core,
                'schema' => $blockType->schema ?? [],
            ];
        }
    }

    /**
     * Clear the cached block registry data.
     */
    public static function clearCache(): void
    {
        cache()->forget('block_registry.types');
    }

    protected function ensureCoreTypes(): void
    {
        $core = [
            // Layout blocks
            ['slug' => 'container', 'name' => 'Container', 'category' => 'layout', 'icon' => 'bi-box', 'schema' => ['children' => true, 'settings' => ['class' => '', 'padding' => 'py-8', 'full_width' => true, 'max_width' => 'max-w-7xl', 'background' => '']]],
            ['slug' => 'columns', 'name' => 'Columns', 'category' => 'layout', 'icon' => 'bi-layout-three-columns', 'schema' => ['children' => true, 'settings' => ['columns' => 2, 'gap' => 'gap-6', 'class' => '']]],
            ['slug' => 'section', 'name' => 'Section', 'category' => 'layout', 'icon' => 'bi-layout-text-window', 'schema' => ['children' => true, 'settings' => ['class' => '', 'background' => '', 'padding' => 'py-12', 'style' => '', 'align' => '']]],
            ['slug' => 'hero', 'name' => 'Hero', 'category' => 'layout', 'icon' => 'square-mouse-pointer', 'schema' => ['data' => ['eyebrow' => '', 'title' => '', 'text' => '', 'primary_text' => '', 'primary_url' => '', 'secondary_text' => '', 'secondary_url' => '', 'image' => '', 'image_alt' => ''], 'settings' => ['layout' => 'split', 'style' => 'glow', 'size' => 'normal', 'grid' => true]]],
            ['slug' => 'spacer', 'name' => 'Spacer', 'category' => 'layout', 'icon' => 'bi-distribute-vertical', 'schema' => ['settings' => ['height' => '2rem']]],
            ['slug' => 'divider', 'name' => 'Divider', 'category' => 'layout', 'icon' => 'bi-hr', 'schema' => ['settings' => ['class' => '']]],

            // Content blocks
            ['slug' => 'heading', 'name' => 'Heading', 'category' => 'content', 'icon' => 'bi-type-h1', 'schema' => ['data' => ['text' => '', 'level' => 'h2', 'alignment' => 'left']]],
            ['slug' => 'text', 'name' => 'Text', 'category' => 'content', 'icon' => 'bi-text-paragraph', 'schema' => ['data' => ['content' => '']]],
            ['slug' => 'image', 'name' => 'Image', 'category' => 'content', 'icon' => 'bi-image', 'schema' => ['data' => ['src' => '', 'alt' => '', 'caption' => ''], 'settings' => ['class' => '', 'width' => 'full']]],
            ['slug' => 'video', 'name' => 'Video', 'category' => 'content', 'icon' => 'bi-play-circle', 'schema' => ['data' => ['url' => '', 'poster' => ''], 'settings' => ['autoplay' => false, 'controls' => true]]],
            ['slug' => 'quote', 'name' => 'Quote', 'category' => 'content', 'icon' => 'bi-quote', 'schema' => ['data' => ['text' => '', 'author' => '', 'cite' => '']]],
            ['slug' => 'code', 'name' => 'Code', 'category' => 'content', 'icon' => 'bi-code-slash', 'schema' => ['data' => ['code' => '', 'language' => 'html']]],
            ['slug' => 'stats', 'name' => 'Key Figures', 'category' => 'content', 'icon' => 'chart-column', 'schema' => ['data' => ['items' => []], 'settings' => ['style' => 'cards', 'align' => 'center']]],
            ['slug' => 'pricing', 'name' => 'Pricing Table', 'category' => 'content', 'icon' => 'tag', 'schema' => ['data' => ['plans' => []]]],
            ['slug' => 'logos', 'name' => 'Logo Strip', 'category' => 'content', 'icon' => 'images', 'schema' => ['data' => ['items' => []], 'settings' => ['style' => 'muted']]],
            ['slug' => 'list', 'name' => 'List', 'category' => 'content', 'icon' => 'bi-list-ul', 'schema' => ['data' => ['items' => [], 'style' => 'unordered']]],

            // Interactive blocks
            ['slug' => 'accordion', 'name' => 'Accordion', 'category' => 'interactive', 'icon' => 'bi-chevron-bar-expand', 'schema' => ['data' => ['items' => []], 'settings' => ['allow_multiple' => false]]],
            ['slug' => 'tabs', 'name' => 'Tabs', 'category' => 'interactive', 'icon' => 'bi-window-stack', 'schema' => ['data' => ['tabs' => []]]],
            ['slug' => 'button', 'name' => 'Button', 'category' => 'interactive', 'icon' => 'bi-hand-index', 'schema' => ['data' => ['text' => 'Click me', 'url' => '#', 'style' => 'primary', 'target' => '_self']]],
            ['slug' => 'buttons', 'name' => 'Buttons', 'category' => 'interactive', 'icon' => 'mouse-pointer-click', 'schema' => ['data' => ['items' => []], 'settings' => ['size' => '', 'align' => '']]],
            ['slug' => 'link-group', 'name' => 'Link Group', 'category' => 'interactive', 'icon' => 'bi-link-45deg', 'schema' => ['data' => ['links' => []]]],
            ['slug' => 'alert', 'name' => 'Alert', 'category' => 'interactive', 'icon' => 'bi-exclamation-triangle', 'schema' => ['data' => ['title' => '', 'content' => '', 'type' => 'info']]],
            ['slug' => 'card', 'name' => 'Card', 'category' => 'interactive', 'icon' => 'bi-card-heading', 'schema' => ['data' => ['title' => '', 'content' => '', 'image' => '', 'url' => ''], 'settings' => ['class' => '']]],
        ];

        // Add core types from later versions (e.g. hero, stats)
        $existing = BlockType::whereIn('slug', array_column($core, 'slug'))->pluck('slug')->all();

        foreach ($core as $type) {
            if (in_array($type['slug'], $existing, true)) {
                continue;
            }

            BlockType::firstOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'category' => $type['category'],
                    'icon' => $type['icon'],
                    'is_core' => true,
                    'schema' => $type['schema'],
                ]
            );
        }
    }

    public function register(string $slug, array $definition): void
    {
        $this->types[$slug] = $definition;
    }

    public function all(): array
    {
        return $this->types;
    }

    public function get(string $slug): ?array
    {
        return $this->types[$slug] ?? null;
    }

    public function has(string $slug): bool
    {
        return isset($this->types[$slug]);
    }

    public function byCategory(): array
    {
        $grouped = [];
        foreach ($this->types as $slug => $type) {
            $category = $type['category'] ?? 'other';
            $grouped[$category][$slug] = $type;
        }

        return $grouped;
    }
}
