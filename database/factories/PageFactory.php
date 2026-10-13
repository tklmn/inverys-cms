<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(3, true),
            'content_type' => 'html',
            'blocks' => null,
            'parent_id' => null,
            'level' => 0,
            'order' => 0,
            'is_active' => true,
            'hide_in_menu' => false,
            'is_root' => false,
            'meta_title' => fake()->optional()->sentence(),
            'meta_description' => fake()->optional()->text(160),
        ];
    }

    public function root(): static
    {
        return $this->state([
            'is_root' => true,
            'slug' => '/',
            'title' => 'Home',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withBlocks(array $blocks = []): static
    {
        return $this->state([
            'content_type' => 'blocks',
            'blocks' => $blocks ?: [
                [
                    'id' => 'block-'.Str::random(8),
                    'type' => 'text',
                    'data' => ['content' => '<p>Test block content</p>'],
                    'settings' => [],
                ],
            ],
        ]);
    }

    public function childOf(Page $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
            'level' => $parent->level + 1,
        ]);
    }
}
