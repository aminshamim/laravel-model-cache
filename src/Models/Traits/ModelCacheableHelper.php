<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Models\Traits;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait ModelCacheableHelper
{
    /**
     * Cache key generator instance.
     */
    private static ?object $cacheKeyGenerator = null;

    /**
     * Get cache key for a specific ID or model instance.
     */
    public function getCacheKey($id = null): string
    {
        $properties = $this->getCacheableProperties();
        $prefix = $properties['prefix'] ?? config('model-cache.prefix', 'model-cache');
        $modelClass = static::class;
        $primaryValue = $id ?? $this->getKey();

        // Create a more unique and collision-resistant cache key
        $hashedClass = md5($modelClass);

        return "{$prefix}:{$hashedClass}:{$primaryValue}";
    }

    /**
     * Get cache instance.
     */
    public function getCacheInstance(): Repository
    {
        $properties = $this->getCacheableProperties();
        $driver = $properties['driver'] ?? config('model-cache.driver') ?? config('cache.default');

        return Cache::driver($driver);
    }

    /**
     * Log cache operations with improved context.
     */
    public function log(string $message, string $level = 'debug', array $context = []): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        $properties = $this->getCacheableProperties();
        $channel = $properties['logging']['channel'] ?? config('model-cache.logging.channel') ?? config('logging.default');
        $logLevel = $properties['logging']['level'] ?? config('model-cache.logging.level', 'debug');

        $enrichedContext = array_merge([
            'package' => 'laravel-model-cache',
            'model_class' => static::class,
            'timestamp' => now()->toISOString(),
        ], $context);

        Log::channel($channel)->log($logLevel, "[ModelCache] {$message}", $enrichedContext);
    }

    /**
     * Check if logging is enabled.
     */
    private function shouldLog(): bool
    {
        $properties = $this->getCacheableProperties();

        return $properties['logging']['enabled'] ?? config('model-cache.logging.enabled', false);
    }

    /**
     * Get cache statistics for this model class.
     */
    public function getCacheStatistics(): array
    {
        $cache = $this->getCacheInstance();
        $statsKey = 'model-cache:stats:'.str_replace('\\', '_', static::class);

        return $cache->get($statsKey, [
            'hits' => 0,
            'misses' => 0,
            'last_hit' => null,
            'last_miss' => null,
        ]);
    }

    /**
     * Generate cache tags for better cache management.
     */
    public function getCacheTags(): array
    {
        $properties = $this->getCacheableProperties();
        $prefix = $properties['prefix'] ?? config('model-cache.prefix', 'model-cache');
        $modelClass = str_replace('\\', '_', static::class);

        return [
            $prefix,
            "{$prefix}:{$modelClass}",
            'model-cache:all',
        ];
    }

    /**
     * Get cacheable properties configuration.
     * This method should be implemented by the using class.
     */
    abstract public function getCacheableProperties(): array;
}
