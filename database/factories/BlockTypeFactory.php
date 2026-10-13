<?php

namespace Database\Factories;

use App\Models\BlockType;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockTypeFactory extends Factory
{
    protected $model = BlockType::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(['content', 'layout', 'media', 'interactive']),
            'icon' => 'bi-square',
            'is_core' => true,
            'plugin_slug' => null,
            'schema' => null,
        ];
    }

    public function plugin(string $slug): static
    {
        return $this->state([
            'is_core' => false,
            'plugin_slug' => $slug,
        ]);
    }
}
