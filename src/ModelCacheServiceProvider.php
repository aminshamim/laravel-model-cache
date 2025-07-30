<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache;

use AminShamim\LaravelModelCache\Contracts\CachePerformanceServiceInterface;
use AminShamim\LaravelModelCache\Contracts\ModelCacheServiceInterface;
use AminShamim\LaravelModelCache\Services\CachePerformanceService;
use AminShamim\LaravelModelCache\Services\ModelCacheService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ModelCacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/model-cache.php', 'model-cache'
        );

        // Bind interfaces to implementations
        $this->app->bind(CachePerformanceServiceInterface::class, function ($app) {
            return CachePerformanceService::make();
        });

        $this->app->bind(ModelCacheServiceInterface::class, function ($app) {
            return ModelCacheService::make(
                $app->make(CachePerformanceServiceInterface::class)
            );
        });

        // Register alias for facade
        $this->app->alias(ModelCacheServiceInterface::class, 'model-cache');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->publishMigrations();
    }

    /**
     * Publish configuration file.
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../config/model-cache.php' => config_path('model-cache.php'),
        ], 'model-cache-config');
    }

    /**
     * Publish migrations if needed.
     */
    protected function publishMigrations(): void
    {
        // Add any migrations here if needed in the future
        // $this->publishes([
        //     __DIR__.'/../database/migrations' => database_path('migrations'),
        // ], 'model-cache-migrations');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            CachePerformanceServiceInterface::class,
            ModelCacheServiceInterface::class,
            'model-cache',
        ];
    }
}
