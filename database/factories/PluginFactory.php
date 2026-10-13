<?php

namespace Database\Factories;

use App\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;

class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(1),
            'name' => fake()->words(2, true),
            'version' => '1.0.0',
            'description' => fake()->sentence(),
            'author' => fake()->name(),
            'is_enabled' => false,
            'installed_at' => now(),
            'is_system' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state([
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }
}
