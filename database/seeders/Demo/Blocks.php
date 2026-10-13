<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * Shorthand for the page builder blocks of the demo content.
 *
 * Produces the same structure the builder stores — styling is done only
 * through the block options (background, alignment, icons …), never through
 * Tailwind classes. That way the demo pages show what editors can do themselves.
 *
 * IDs are numbered per page (Blocks::page('hp') → hp-1, hp-2 …) and
 * therefore stay the same on every run.
 */
final class Blocks
{
    private static string $prefix = 'demo';

    private static int $counter = 0;

    public static function page(string $prefix): void
    {
        self::$prefix = $prefix;
        self::$counter = 0;
    }

    private static function id(): string
    {
        return self::$prefix.'-'.(++self::$counter);
    }

    private static function block(string $type, array $data = [], array $settings = [], ?array $children = null): array
    {
        $block = ['id' => self::id(), 'type' => $type, 'data' => $data, 'settings' => $settings + ['class' => '']];

        if ($children !== null) {
            $block['children'] = array_values($children);
        }

        return $block;
    }

    // ── Layout ───────────────────────────────────────────────────

    /**
     * Full-width band with a surface, containing a container at content width.
     *
     * @param  string  $style  '' | soft | tint | glow | gradient | dark
     */
    public static function band(string $style, array $children, string $padding = 'py-16', string $width = 'max-w-7xl', string $align = '', string $anchor = ''): array
    {
        return self::block('container', [], ['full_width' => true, 'padding' => $padding, 'style' => $style, 'align' => $align, 'anchor' => $anchor, 'background' => ''], [
            self::block('container', [], ['full_width' => false, 'max_width' => $width, 'padding' => 'py-0', 'background' => ''], self::flatten($children)),
        ]);
    }

    /**
     * Columns; each column is a list of blocks.
     *
     * @param  list<list<array>|array>  $columns
     */
    public static function columns(array $columns, string $gap = 'gap-6', string $valign = ''): array
    {
        $children = array_map(fn ($blocks) => self::block('column', [], [], self::flatten(isset($blocks['type']) ? [$blocks] : $blocks)), $columns);

        return self::block('columns', [], ['columns' => (string) count($columns), 'gap' => $gap, 'valign' => $valign], $children);
    }

    public static function spacer(string $height = '1rem'): array
    {
        return self::block('spacer', [], ['height' => $height]);
    }

    // ── Content ──────────────────────────────────────────────────

    public static function heading(string $text, string $level = 'h2', string $alignment = 'left', string $eyebrow = '', string $style = ''): array
    {
        return self::block('heading', ['text' => $text, 'level' => $level, 'alignment' => $alignment, 'eyebrow' => $eyebrow], ['style' => $style]);
    }

    public static function text(string $html, string $size = '', string $align = ''): array
    {
        return self::block('text', ['content' => $html], ['size' => $size, 'align' => $align]);
    }

    /**
     * Section header: eyebrow, heading and lead.
     *
     * @return list<array>
     */
    public static function intro(string $eyebrow, string $title, string $lead = '', string $align = 'center'): array
    {
        $blocks = [self::heading($title, 'h2', $align, $eyebrow)];

        if ($lead !== '') {
            $blocks[] = self::text('<p>'.$lead.'</p>', 'lead', $align === 'left' ? '' : $align);
        }

        $blocks[] = self::spacer('1rem');

        return $blocks;
    }

    /**
     * @param  array{title?: string, content?: string, icon?: string, image?: string, url?: string, link_text?: string}  $card
     * @param  array{style?: string, align?: string, icon_style?: string}  $options
     */
    public static function card(array $card, array $options = []): array
    {
        return self::block('card', $card + ['title' => '', 'content' => '', 'image' => '', 'url' => '', 'icon' => '', 'link_text' => ''], $options + ['style' => '', 'align' => '', 'icon_style' => '']);
    }

    /**
     * Card grid: one column per card.
     *
     * @param  list<array>  $cards
     */
    public static function cards(array $cards, array $options = [], string $gap = 'gap-6'): array
    {
        return self::columns(array_map(fn ($card) => [self::card($card, $options)], $cards), $gap);
    }

    public static function button(string $text, string $url, string $style = 'primary', array $options = [], string $icon = ''): array
    {
        return self::block('button', ['text' => $text, 'url' => $url, 'style' => $style, 'target' => str_starts_with($url, 'http') ? '_blank' : '_self', 'icon' => $icon], $options + ['size' => '', 'align' => '']);
    }

    public static function image(string $src, string $alt, string $caption = '', string $style = ''): array
    {
        return self::block('image', ['src' => $src, 'alt' => $alt, 'caption' => $caption], ['width' => 'full', 'style' => $style]);
    }

    public static function quote(string $text, string $author, string $cite = '', string $image = ''): array
    {
        return self::block('quote', ['text' => $text, 'author' => $author, 'cite' => $cite, 'image' => $image]);
    }

    /**
     * Several buttons side by side.
     *
     * @param  list<array{0: string, 1: string, 2?: string, 3?: string}>  $items  [text, URL, style, icon]
     */
    public static function buttons(array $items, string $align = '', string $size = ''): array
    {
        return self::block('buttons', ['items' => array_map(fn ($item) => ['text' => $item[0], 'url' => $item[1], 'style' => $item[2] ?? 'primary', 'icon' => $item[3] ?? ''], $items)], ['align' => $align, 'size' => $size]);
    }

    /**
     * Pricing table.
     *
     * @param  list<array{name: string, price?: string, period?: string, description?: string, features?: list<string>, button_text?: string, button_url?: string, featured?: bool, badge?: string}>  $plans
     */
    public static function pricing(array $plans): array
    {
        return self::block('pricing', ['plans' => array_map(fn ($plan) => ['features' => implode("\n", $plan['features'] ?? [])] + $plan + [
            'price' => '', 'period' => '', 'description' => '', 'button_text' => '', 'button_url' => '#', 'featured' => false, 'badge' => '',
        ], $plans)]);
    }

    /** @param  list<array{0: string, 1: string}>  $items  [image, alt text] */
    public static function logos(array $items, string $style = 'muted'): array
    {
        return self::block('logos', ['items' => array_map(fn ($item) => ['image' => $item[0], 'alt' => $item[1], 'url' => ''], $items)], ['style' => $style]);
    }

    /** @param  list<string>  $items */
    public static function list(array $items, string $style = 'unordered'): array
    {
        return self::block('list', ['items' => $items, 'style' => $style]);
    }

    public static function alert(string $title, string $content, string $type = 'info'): array
    {
        return self::block('alert', ['title' => $title, 'content' => $content, 'type' => $type]);
    }

    /** @param  list<array{title: string, content: string}>  $items */
    public static function accordion(array $items): array
    {
        return self::block('accordion', ['items' => $items]);
    }

    /** @param  list<array{text: string, url: string}>  $links */
    public static function links(array $links): array
    {
        return self::block('link-group', ['links' => $links]);
    }

    public static function code(string $code, string $language): array
    {
        return self::block('code', ['code' => $code, 'language' => $language]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $items  [value, label]
     */
    public static function stats(array $items, string $style = 'cards', string $align = 'center'): array
    {
        return self::block('stats', ['items' => array_map(fn ($item) => ['value' => $item[0], 'label' => $item[1]], $items)], ['style' => $style, 'align' => $align]);
    }

    /**
     * Large page opener.
     *
     * @param  array{eyebrow?: string, title?: string, text?: string, primary_text?: string, primary_url?: string, secondary_text?: string, secondary_url?: string, image?: string, image_alt?: string}  $data
     * @param  array{layout?: string, style?: string, size?: string, level?: string, grid?: bool}  $options
     */
    public static function hero(array $data, array $options = []): array
    {
        $data += ['eyebrow' => '', 'title' => '', 'text' => '', 'primary_text' => '', 'primary_url' => '', 'secondary_text' => '', 'secondary_url' => '', 'image' => '', 'image_alt' => ''];

        return self::block('hero', $data, $options + ['layout' => $data['image'] !== '' ? 'split' : 'centered', 'style' => 'glow', 'size' => 'normal', 'level' => 'h1', 'grid' => true]);
    }

    /**
     * Call to action at the end of the page: compact hero with H2 on a gradient.
     */
    public static function cta(string $title, string $text, string $primaryText, string $primaryUrl, string $secondaryText = '', string $secondaryUrl = ''): array
    {
        return self::hero(
            ['title' => $title, 'text' => $text, 'primary_text' => $primaryText, 'primary_url' => $primaryUrl, 'secondary_text' => $secondaryText, 'secondary_url' => $secondaryUrl],
            ['layout' => 'centered', 'style' => 'gradient', 'size' => 'compact', 'level' => 'h2', 'grid' => false],
        );
    }

    /** Flatten nested lists (e.g. from intro()) into a block list */
    private static function flatten(array $blocks): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            if (isset($block['type'])) {
                $flat[] = $block;
            } elseif (is_array($block)) {
                array_push($flat, ...self::flatten($block));
            }
        }

        return $flat;
    }
}
