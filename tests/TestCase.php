<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests;

use AminShamim\LaravelModelCache\ModelCacheServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
        $this->setUpFactories();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ModelCacheServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('cache.default', 'array');
        config()->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        config()->set('model-cache.logging.enabled', false);
        config()->set('model-cache.debug_mode_enabled', false);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_related_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_model_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    protected function setUpFactories(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AminShamim\\LaravelModelCache\\Tests\\Factories\\'.class_basename($modelName).'Factory'
        );
    }
}
