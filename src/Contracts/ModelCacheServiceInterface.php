<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Contracts;

use Illuminate\Contracts\Cache\Repository;

interface ModelCacheServiceInterface
{
    /**
     * Warm up cache for multiple models.
     */
    public function warmCache(string $modelClass, array $ids = []): int;

    /**
     * Get cache statistics for a model.
     */
    public function getCacheStats(string $modelClass): array;

    /**
     * Clear cache for specific records.
     */
    public function clearCacheForRecords(string $modelClass, array $ids): bool;

    /**
     * Optimize cache by removing old records.
     */
    public function optimizeCache(string $modelClass): array;

    /**
     * Get cache instance for the given driver.
     */
    public function getCacheInstance(?string $driver = null): Repository;
}
