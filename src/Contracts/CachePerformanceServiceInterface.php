<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Contracts;

interface CachePerformanceServiceInterface
{
    /**
     * Record a cache hit for the given model class.
     */
    public function recordHit(string $modelClass): void;

    /**
     * Record a cache miss for the given model class.
     */
    public function recordMiss(string $modelClass): void;

    /**
     * Get the cache hit rate for the given model class.
     */
    public function getHitRate(string $modelClass): float;

    /**
     * Get dynamic TTL based on cache performance.
     */
    public function getDynamicTTL(string $modelClass, int $defaultTTL): int;

    /**
     * Reset statistics for the given model class.
     */
    public function resetStats(string $modelClass): void;

    /**
     * Get all performance statistics.
     */
    public function getAllStats(): array;
}
