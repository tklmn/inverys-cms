<?php

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token' => hash('sha256', Str::random(64)),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Create a token and return the plain text for use in tests.
     */
    public static function createWithPlainText(User $user, array $attributes = []): array
    {
        $plain = Str::random(64);

        $token = ApiToken::create(array_merge([
            'user_id' => $user->id,
            'name' => 'test-token',
            'token' => hash('sha256', $plain),
            'abilities' => ['*'],
            'expires_at' => now()->addDay(),
        ], $attributes));

        return ['token' => $token, 'plain_text' => $plain];
    }
}
