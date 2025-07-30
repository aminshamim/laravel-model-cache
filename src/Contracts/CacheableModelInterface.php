<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Contracts;

interface CacheableModelInterface
{
    /**
     * Get the cacheable properties configuration.
     */
    public function getCacheableProperties(): array;

    /**
     * Check if this model should be cached.
     */
    public function shouldCache(): bool;

    /**
     * Cache this model instance.
     */
    public function cacheRecord(): bool;

    /**
     * Remove this model from cache.
     */
    public function forgetCache(): bool;

    /**
     * Get the cache key for this model.
     */
    public function getCacheKey(): string;
}
