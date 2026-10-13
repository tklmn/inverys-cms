<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Starter templates for new pages ("New page" dialog).
 *
 * A template is just the initial set of blocks — after that everything is
 * freely editable. Texts are in the interface language so the new page does
 * not start with English placeholders.
 */
final class PageStarters
{
    /** @return array<string, array{icon: string, label: string, text: string}> */
    public static function all(): array
    {
        return [
            'blank' => ['icon' => 'square-dashed', 'label' => __('translation.pages.starter_blank'), 'text' => __('translation.pages.starter_blank_text')],
            'text' => ['icon' => 'align-left', 'label' => __('translation.pages.starter_text'), 'text' => __('translation.pages.starter_text_text')],
            'landing' => ['icon' => 'layout-template', 'label' => __('translation.pages.starter_landing'), 'text' => __('translation.pages.starter_landing_text')],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function blocks(string $starter, string $title): array
    {
        return match ($starter) {
            'text' => [Page::textBlock('<p>'.e(__('translation.pages.starter_text_placeholder')).'</p>')],
            'landing' => self::landing($title),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private static function landing(string $title): array
    {
        $column = fn (string $icon, string $heading) => self::block('column', [], ['class' => ''], [
            self::block('card', [
                'title' => $heading,
                'content' => __('translation.pages.starter_card_text'),
                'icon' => $icon,
                'image' => '', 'url' => '', 'link_text' => '',
            ], ['class' => '', 'style' => '', 'align' => '', 'icon_style' => '']),
        ]);

        return [
            self::block('hero', [
                'eyebrow' => '',
                'title' => $title,
                'text' => __('translation.pages.starter_hero_text'),
                'primary_text' => __('translation.pages.starter_hero_button'),
                'primary_url' => '#',
                'secondary_text' => '', 'secondary_url' => '', 'image' => '', 'image_alt' => '',
            ], ['class' => '', 'layout' => 'centered', 'style' => 'glow', 'size' => 'normal', 'grid' => true, 'level' => 'h1']),
            self::block('section', [], ['class' => '', 'background' => '', 'padding' => 'py-16', 'style' => '', 'align' => ''], [
                self::block('heading', ['text' => __('translation.pages.starter_features_heading'), 'level' => 'h2', 'alignment' => 'center'], ['class' => '']),
                self::block('columns', [], ['class' => '', 'columns' => '3', 'gap' => 'gap-6'], [
                    $column('sparkles', __('translation.pages.starter_card_1')),
                    $column('shield-check', __('translation.pages.starter_card_2')),
                    $column('zap', __('translation.pages.starter_card_3')),
                ]),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>|null  $children
     * @return array<string, mixed>
     */
    private static function block(string $type, array $data, array $settings, ?array $children = null): array
    {
        $block = ['id' => 'block-'.Str::lower(Str::random(9)), 'type' => $type, 'data' => $data, 'settings' => $settings];

        if ($children !== null) {
            $block['children'] = $children;
        }

        return $block;
    }
}
