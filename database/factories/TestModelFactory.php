<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Database\Factories;

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
            'is_active' => true, // Default to active so caching works
            'metadata' => [
                'created_at' => now()->toISOString(),
                'source' => 'factory',
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
