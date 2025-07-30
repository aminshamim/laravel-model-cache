<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Factories;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'is_active' => true,
            'metadata' => [
                'role' => $this->faker->randomElement(['admin', 'user', 'moderator']),
                'preferences' => [
                    'theme' => $this->faker->randomElement(['light', 'dark']),
                    'notifications' => $this->faker->boolean(),
                ],
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
