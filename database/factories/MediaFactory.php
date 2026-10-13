<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $name = fake()->word();

        return [
            'name' => $name,
            'file_name' => Str::uuid().'.jpg',
            'path' => 'media/'.date('Y/m').'/'.Str::uuid().'.jpg',
            'thumbnail_path' => null,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'alt_text' => fake()->optional()->sentence(),
            'description' => fake()->optional()->text(100),
            'user_id' => User::factory(),
        ];
    }

    public function image(): static
    {
        return $this->state([
            'mime_type' => 'image/jpeg',
            'file_name' => Str::uuid().'.jpg',
        ]);
    }

    public function pdf(): static
    {
        return $this->state([
            'mime_type' => 'application/pdf',
            'file_name' => Str::uuid().'.pdf',
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'mime_type' => 'video/mp4',
            'file_name' => Str::uuid().'.mp4',
        ]);
    }
}
