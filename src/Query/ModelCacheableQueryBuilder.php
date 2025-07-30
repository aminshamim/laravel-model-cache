<?php

namespace AminShamim\LaravelModelCache\Query;

use AminShamim\LaravelModelCache\Models\Traits\ModelCacheableHelper;
use AminShamim\LaravelModelCache\Services\CachePerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ModelCacheableQueryBuilder extends Builder
{
    use ModelCacheableHelper;

    /**
     * The model instance being queried.
     */
    protected $model;

    /**
     * Cache configuration for this query builder
     */
    protected $cacheConfig = [];

    /**
     * Create a new query builder instance.
     */
    public function __construct($query, Model $model)
    {
        parent::__construct($query, $model);
        $this->model = $model;
        $this->cacheConfig = $model->getCacheableProperties();
    }

    /**
     * Find a model by its primary key with caching.
     */
    public function findCached($id, $columns = ['*'])
    {
        if ($id === null) {
            return null;
        }

        $cacheKey = $this->getCacheKey($id);
        $modelClass = get_class($this->model);

        try {
            $cache = $this->getCacheInstance();
            $performanceService = CachePerformanceService::make($cache);
            $cached = $cache->get($cacheKey);

            if ($cached !== null) {
                $performanceService->recordHit($modelClass);

                $this->log('Model found in cache', 'debug', [
                    'model' => $modelClass,
                    'id' => $id,
                    'key' => $cacheKey,
                ]);

                $instance = $modelClass::createFromCacheData($cached);
                if ($instance !== null) {
                    return $instance;
                }
            }

            $performanceService->recordMiss($modelClass);

            // Not in cache, fetch from database
            $instance = $this->findWithoutCache($id, ['*']);

            // Auto-cache the result if it exists and should be cached
            if ($instance && $this->shouldCacheModel($instance)) {
                $instance->cacheRecord();
                $this->log('Model fetched from database and cached', 'debug', [
                    'model' => get_class($this->model),
                    'id' => $id,
                    'key' => $cacheKey,
                ]);
            }

            return $instance;
        } catch (\Exception $e) {
            $this->log('Cache error, falling back to database: '.$e->getMessage(), 'error');

            return $this->findWithoutCache($id, $columns);
        }
    }

    /**
     * Override the default find method to use caching.
     */
    public function find($id, $columns = ['*'])
    {
        // Check if caching is enabled for this model
        $properties = $this->model->getCacheableProperties();
        $overrideFind = $properties['override_find_method'] ?? false;

        if ($overrideFind) {
            return $this->findCached($id, ['*']);
        }

        return parent::find($id, ['*']);
    }

    /**
     * Override the default findMany method to use caching.
     */
    public function findMany($ids, $columns = ['*'])
    {
        // Check if caching is enabled for this model
        $properties = $this->model->getCacheableProperties();
        $overrideFind = $properties['override_find_method'] ?? false;

        if ($overrideFind) {
            return $this->findManyCached($ids, ['*']);
        }

        return parent::findMany($ids, ['*']);
    }

    /**
     * Find a model by its primary key without caching.
     */
    public function findWithoutCache($id, $columns = ['*'])
    {
        return parent::find($id, $columns);
    }

    /**
     * Find multiple models by primary keys with caching.
     */
    public function findManyCached(array $ids, $columns = ['*'])
    {
        if (empty($ids)) {
            return $this->model->newCollection();
        }

        $cache = $this->getCacheInstance();
        $performanceService = CachePerformanceService::make($cache);
        $modelClass = get_class($this->model);

        $cached = [];
        $missing = [];
        $cacheKeys = [];

        // Prepare cache keys for all IDs
        foreach ($ids as $id) {
            $cacheKeys[$id] = $this->getCacheKey($id);
        }

        // Try to get all records at once
        if (method_exists($cache, 'many')) {
            $cachedData = $cache->many($cacheKeys);
        } else {
            $cachedData = [];
            foreach ($cacheKeys as $id => $key) {
                $cachedData[$key] = $cache->get($key);
            }
        }

        // Process cached data
        foreach ($ids as $id) {
            $key = $cacheKeys[$id];
            $data = $cachedData[$key] ?? null;

            if ($data !== null) {
                $instance = $modelClass::createFromCacheData($data);
                if ($instance !== null) {
                    $cached[$id] = $instance;
                    $performanceService->recordHit($modelClass);

                    continue;
                }
            }

            $missing[] = $id;
            $performanceService->recordMiss($modelClass);
        }

        // Fetch missing records from database
        if (! empty($missing)) {
            $dbRecords = $this->whereIn($this->model->getKeyName(), $missing)->get($columns);
            $toCache = [];
            $properties = $this->getCacheableProperties();
            $ttl = $properties['ttl'] ?? config('model-cache.ttl', 300);

            foreach ($dbRecords as $record) {
                $id = $record->getKey();
                $cached[$id] = $record;

                // Cache the record using the model's own method
                if ($this->shouldCacheModel($record)) {
                    $record->cacheRecord();
                }
            }
        }

        return $this->model->newCollection($cached);
    }

    /**
     * Check if a model should be cached.
     */
    protected function shouldCacheModel($model): bool
    {
        if (! method_exists($model, 'shouldCache')) {
            return true;
        }

        return $model->shouldCache();
    }

    /**
     * Prepare basic cache data for a model.
     */
    protected function prepareBasicCacheData($model): array
    {
        return [
            'attributes' => $model->getAttributes(),
            'original' => $model->getOriginal(),
            'relations' => [],
            'cached_at' => now()->toISOString(),
            'cache_version' => '2.0',
        ];
    }

    /**
     * Warm up cache for this query.
     */
    public function warmCache(int $limit = 1000): int
    {
        $records = $this->limit($limit)->get();
        $cache = $this->getCacheInstance();
        $properties = $this->getCacheableProperties();
        $ttl = $properties['ttl'] ?? config('model-cache.ttl', 300);
        $cachedCount = 0;

        foreach ($records as $record) {
            if (method_exists($record, 'shouldCache') && $record->shouldCache()) {
                if ($record->cacheRecord()) {
                    $cachedCount++;
                }
            }
        }

        return $cachedCount;
    }

    /**
     * Clear cache for all records matching this query.
     */
    public function clearCache(): bool
    {
        try {
            // Get all matching records to clear their cache
            $records = $this->get([$this->model->getKeyName()]);
            $cache = $this->getCacheInstance();
            $success = true;

            foreach ($records as $record) {
                if (! $record->forgetCache()) {
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            $this->log('Failed to clear cache: '.$e->getMessage(), 'error');

            return false;
        }
    }

    /**
     * Get cacheable properties from the model.
     */
    public function getCacheableProperties(): array
    {
        return $this->model->getCacheableProperties();
    }
}
