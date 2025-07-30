<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Factories;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\Models\TestRelatedModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestRelatedModelFactory extends Factory
{
    protected $model = TestRelatedModel::class;

    public function definition(): array
    {
        return [
            'test_model_id' => TestModel::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
        ];
    }
}
