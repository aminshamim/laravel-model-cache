<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Models\Traits;

use AminShamim\LaravelModelCache\Query\ModelCacheableQueryBuilder;
use AminShamim\LaravelModelCache\Services\CachePerformanceService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * @mixin Model
 */
trait ModelCacheable
{
    use ModelCacheableHelper;

    /**
     * Boot the ModelCacheable trait for the model.
     */
    public static function bootModelCacheable(): void
    {
        static::saved(static function (Model $model): void {

            // Check if the model should cache and auto-invalidation is enabled
            if (method_exists($model, 'shouldCache') && $model->shouldCache()) {
                $properties = $model->getCacheableProperties();
                $autoInvalidate = $properties['auto_invalidate'] ?? config('model-cache.auto_invalidate', true);

                if ($autoInvalidate) {
                    $model->cacheRecord();

                    if (config('model-cache.debug_mode_enabled', false)) {
                        Log::info('Model saved and cached', [
                            'model' => get_class($model),
                            'id' => $model->getKey(),
                            'was_recently_created' => $model->wasRecentlyCreated,
                        ]);
                    }
                }
            }
        });

        static::deleted(static function (Model $model): void {
            if (method_exists($model, 'forgetCache')) {
                $properties = $model->getCacheableProperties();
                $autoInvalidate = $properties['auto_invalidate'] ?? config('model-cache.auto_invalidate', true);

                if ($autoInvalidate) {
                    $model->forgetCache();

                    if (config('model-cache.debug_mode_enabled', false)) {
                        Log::info('Model deleted and cache cleared', [
                            'model' => get_class($model),
                            'id' => $model->getKey(),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Create a new Eloquent query builder for the model with caching.
     */
    public function newEloquentBuilder($query): Builder
    {
        $properties = $this->getCacheableProperties();
        $overrideFind = $properties['override_find_method'] ?? false;

        if ($overrideFind) {
            return new ModelCacheableQueryBuilder($query, $this);
        }

        return parent::newEloquentBuilder($query);
    }

    /**
     * Get cacheable properties configuration with sensible defaults.
     */
    public function getCacheableProperties(): array
    {
        return array_merge([
            'ttl' => config('model-cache.ttl', 300),
            'prefix' => config('model-cache.prefix', 'model-cache'),
            'primary_key' => $this->getKeyName(),
            'auto_invalidate' => config('model-cache.auto_invalidate', true),
            'logging' => [
                'enabled' => config('model-cache.logging.enabled', false),
                'channel' => config('model-cache.logging.channel'),
                'level' => config('model-cache.logging.level', 'debug'),
            ],
            'driver' => config('model-cache.driver'),
            'cache_relationships' => config('model-cache.cache_relationships', false),
            'max_records_per_model' => config('model-cache.max_records_per_model', 0),
            'override_find_method' => config('model-cache.override_find_method', false),
        ], $this->getCustomCacheableProperties());
    }

    /**
     * Override this method in your model to customize caching behavior.
     */
    protected function getCustomCacheableProperties(): array
    {
        return [];
    }

    /**
     * Check if this model should be cached.
     */
    public function shouldCache(): bool
    {
        $primaryValue = $this->getKey();

        if ($primaryValue === null) {
            return false;
        }

        // Additional checks can be added here
        if (method_exists($this, 'trashed') && $this->trashed()) {
            return false;
        }

        return true;
    }

    /**
     * Cache this model instance with improved error handling.
     */
    public function cacheRecord(): bool
    {
        if (! $this->shouldCache()) {
            return false;
        }

        try {
            $properties = $this->getCacheableProperties();
            $cacheKey = $this->getCacheKey();
            $cache = $this->getCacheInstance();

            // Get dynamic TTL based on performance
            $performanceService = CachePerformanceService::make($cache);
            $defaultTTL = $properties['ttl'] ?? config('model-cache.ttl', 300);
            $ttl = $performanceService->getDynamicTTL(static::class, $defaultTTL);

            // Prepare model data for caching
            $cacheData = $this->prepareCacheData();

            // Check if cache driver supports tags (array driver doesn't)
            $driverName = $properties['driver'] ?? config('model-cache.driver') ?? config('cache.default');
            $supportsTags = ! in_array($driverName, ['array', 'database', 'file']);

            // Use cache tags only if supported
            if ($supportsTags && method_exists($cache, 'tags')) {
                $result = $cache->tags($this->getCacheTags())
                    ->put($cacheKey, $cacheData, $ttl);
            } else {
                $result = $cache->put($cacheKey, $cacheData, $ttl);
            }

            $this->log('Model cached successfully', 'debug', [
                'model' => static::class,
                'id' => $this->getKey(),
                'key' => $cacheKey,
                'ttl' => $ttl,
                'success' => $result,
                'supports_tags' => $supportsTags,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->log('Failed to cache model: '.$e->getMessage(), 'error', [
                'model' => static::class,
                'id' => $this->getKey(),
                'exception' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Prepare model data for caching.
     */
    public function prepareCacheData(): array
    {
        $properties = $this->getCacheableProperties();

        $data = [
            'attributes' => $this->getAttributes(),
            'original' => $this->getOriginal(),
            'relations' => [],
            'cached_at' => now()->toISOString(),
            'cache_version' => '2.0',
        ];

        // Cache relationships if enabled
        if ($properties['cache_relationships'] ?? false) {
            $data['relations'] = $this->getRelations();
        }

        return $data;
    }

    /**
     * Remove this model from cache with improved error handling.
     */
    public function forgetCache(): bool
    {
        try {
            $cacheKey = $this->getCacheKey();
            $cache = $this->getCacheInstance();

            // Check if cache driver supports tags
            $properties = $this->getCacheableProperties();
            $driverName = $properties['driver'] ?? config('model-cache.driver') ?? config('cache.default');
            $supportsTags = ! in_array($driverName, ['array', 'database', 'file']);

            // Use cache tags only if supported
            if ($supportsTags && method_exists($cache, 'tags')) {
                $result = $cache->tags($this->getCacheTags())->forget($cacheKey);
            } else {
                $result = $cache->forget($cacheKey);
            }

            $this->log('Model cache cleared', 'debug', [
                'model' => static::class,
                'id' => $this->getKey(),
                'key' => $cacheKey,
                'success' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->log('Failed to clear model cache: '.$e->getMessage(), 'error', [
                'model' => static::class,
                'id' => $this->getKey(),
                'exception' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Find a model by primary key with caching.
     */
    public static function findCached($id, array $columns = ['*']): ?static
    {
        if ($id === null) {
            return null;
        }

        $model = new static;
        $cacheKey = $model->getCacheKey($id);
        $cache = $model->getCacheInstance();
        $performanceService = CachePerformanceService::make($cache);

        try {
            // Check if cache driver supports tags
            $properties = $model->getCacheableProperties();
            $driverName = $properties['driver'] ?? config('model-cache.driver') ?? config('cache.default');
            $supportsTags = ! in_array($driverName, ['array', 'database', 'file']);

            // Try to get from cache first
            if ($supportsTags && method_exists($cache, 'tags')) {
                $cached = $cache->tags($model->getCacheTags())->get($cacheKey);
            } else {
                $cached = $cache->get($cacheKey);
            }

            if ($cached !== null) {
                $performanceService->recordHit(static::class);

                $instance = static::createFromCacheData($cached);
                if ($instance !== null) {
                    $model->log('Model found in cache', 'debug', [
                        'model' => static::class,
                        'id' => $id,
                        'key' => $cacheKey,
                    ]);

                    return $instance;
                }
            }

            $performanceService->recordMiss(static::class);

            // Not in cache, fetch from database
            $instance = static::find($id, $columns);

            // Cache the result if found
            if ($instance && $instance->shouldCache()) {
                $instance->cacheRecord();
            }

            return $instance;
        } catch (Exception $e) {
            $model->log('Cache error, falling back to database: '.$e->getMessage(), 'error');

            return static::find($id, $columns);
        }
    }

    /**
     * Find multiple models by primary keys with caching.
     */
    public static function findManyCached(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        $model = new static;
        $cache = $model->getCacheInstance();
        $performanceService = CachePerformanceService::make($cache);

        $cached = [];
        $missing = [];
        $cacheKeys = [];

        // Prepare cache keys
        foreach ($ids as $id) {
            $cacheKeys[$id] = $model->getCacheKey($id);
        }

        try {
            // Try to get all from cache
            if (method_exists($cache, 'tags') && method_exists($cache, 'many')) {
                $cachedData = $cache->tags($model->getCacheTags())->many($cacheKeys);
            } elseif (method_exists($cache, 'many')) {
                $cachedData = $cache->many($cacheKeys);
            } else {
                $cachedData = [];
                foreach ($cacheKeys as $id => $key) {
                    $cachedData[$key] = $cache->get($key);
                }
            }

            // Process cached results
            foreach ($ids as $id) {
                $key = $cacheKeys[$id];
                $data = $cachedData[$key] ?? null;

                if ($data !== null) {
                    $instance = static::createFromCacheData($data);
                    if ($instance !== null) {
                        $cached[] = $instance;
                        $performanceService->recordHit(static::class);

                        continue;
                    }
                }

                $missing[] = $id;
                $performanceService->recordMiss(static::class);
            }

            // Fetch missing records from database
            if (! empty($missing)) {
                $dbRecords = static::whereIn($model->getKeyName(), $missing)->get($columns);

                foreach ($dbRecords as $record) {
                    $cached[] = $record;
                    if ($record->shouldCache()) {
                        $record->cacheRecord();
                    }
                }
            }

            return new Collection($cached);
        } catch (Exception $e) {
            $model->log('Batch cache error, falling back to database: '.$e->getMessage(), 'error');

            return static::whereIn($model->getKeyName(), $ids)->get($columns);
        }
    }

    /**
     * Create model instance from cached data.
     */
    protected static function createFromCacheData(array $data): ?static
    {
        try {
            $instance = new static;

            // Restore attributes
            $instance->setRawAttributes($data['attributes'] ?? [], true);
            $instance->syncOriginal();

            // Restore relationships if cached
            if (! empty($data['relations'])) {
                foreach ($data['relations'] as $relation => $relationData) {
                    $instance->setRelation($relation, $relationData);
                }
            }

            $instance->exists = true;
            $instance->wasRecentlyCreated = false;

            return $instance;
        } catch (Exception $e) {
            Log::error('Failed to create model from cache data', [
                'model' => static::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Remove all cache entries for this model class.
     */
    public static function forgetAllCache(): bool
    {
        try {
            $model = new static;
            $cache = $model->getCacheInstance();

            // Use cache tags if supported for efficient clearing
            if (method_exists($cache, 'tags')) {
                $result = $cache->tags($model->getCacheTags())->flush();

                $model->log('All cache entries cleared using tags', 'debug', [
                    'model' => static::class,
                    'success' => $result,
                ]);

                return $result;
            }

            // Fallback: this is less efficient but works with all cache drivers
            $model->log('Cache tags not supported, manual clearing required', 'warning', [
                'model' => static::class,
            ]);

            return false;
        } catch (Exception $e) {
            $model = new static;
            $model->log('Failed to clear all cache entries: '.$e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Get cache key with route model binding support.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        // If using primary key, try cache first
        if ($field === $this->getKeyName()) {
            return static::findCached($value);
        }

        // For other fields, fall back to database
        return parent::resolveRouteBinding($value, $field);
    }
}
