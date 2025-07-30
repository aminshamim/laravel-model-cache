<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Services;

use AminShamim\LaravelModelCache\Contracts\CachePerformanceServiceInterface;
use AminShamim\LaravelModelCache\Contracts\ModelCacheServiceInterface;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelCacheService implements ModelCacheServiceInterface
{
    public function __construct(
        private readonly CachePerformanceServiceInterface $performanceService,
        private readonly ?Repository $cache = null
    ) {}

    public function warmCache(string $modelClass, array $ids = []): int
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} is not a valid Eloquent model.");
        }

        /** @var Model $model */
        $model = new $modelClass;

        if (empty($ids)) {
            // Warm cache for all records (be careful with large datasets)
            $records = $model->newQuery()->get();
        } else {
            $records = $model->newQuery()->whereIn($model->getKeyName(), $ids)->get();
        }

        $cachedCount = 0;
        foreach ($records as $record) {
            if (method_exists($record, 'cacheRecord') && $record->cacheRecord()) {
                $cachedCount++;
            }
        }

        return $cachedCount;
    }

    public function getCacheStats(string $modelClass): array
    {
        return [
            'hit_rate' => $this->performanceService->getHitRate($modelClass),
            'model_class' => $modelClass,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function clearCacheForRecords(string $modelClass, array $ids): bool
    {
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} is not a valid Eloquent model.");
        }

        /** @var Model $model */
        $model = new $modelClass;
        $cache = $this->getCacheInstance();
        $success = true;

        foreach ($ids as $id) {
            if (method_exists($model, 'getCacheKey')) {
                $cacheKey = $model->getCacheKey($id);
            } else {
                // Fallback cache key generation
                $prefix = config('model-cache.prefix', 'model-cache');
                $cacheKey = "{$prefix}:{$id}";
            }

            if (! $cache->forget($cacheKey)) {
                $success = false;
            }
        }

        return $success;
    }

    public function optimizeCache(string $modelClass): array
    {
        $maxRecords = config('model-cache.max_records_per_model', 0);

        if ($maxRecords <= 0) {
            return ['status' => 'skipped', 'reason' => 'No limit configured'];
        }

        // This is a simplified implementation
        // In a real scenario, you'd need to track cache keys per model
        return ['status' => 'completed', 'records_removed' => 0];
    }

    public function getCacheInstance(?string $driver = null): Repository
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $driver = $driver ?? config('model-cache.driver') ?? config('cache.default');

        return Cache::driver($driver);
    }

    /**
     * Batch cache multiple models efficiently.
     */
    public function batchCache(Collection $models): int
    {
        $cache = $this->getCacheInstance();
        $cachedCount = 0;
        $batchData = [];

        foreach ($models as $model) {
            if (! method_exists($model, 'shouldCache') || ! $model->shouldCache()) {
                continue;
            }

            try {
                $cacheKey = $model->getCacheKey();
                $properties = $model->getCacheableProperties();
                $ttl = $properties['ttl'] ?? config('model-cache.ttl', 300);

                $batchData[$cacheKey] = [
                    'data' => serialize($model),
                    'ttl' => $ttl,
                ];
            } catch (Exception $e) {
                Log::error('Failed to prepare model for batch caching', [
                    'model' => get_class($model),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Use batch operations if available
        if (method_exists($cache, 'putMany') && ! empty($batchData)) {
            try {
                $cache->putMany(
                    array_map(fn ($item) => $item['data'], $batchData),
                    min(array_column($batchData, 'ttl'))
                );
                $cachedCount = count($batchData);
            } catch (Exception $e) {
                Log::error('Batch cache operation failed', ['error' => $e->getMessage()]);

                // Fallback to individual operations
                foreach ($batchData as $key => $item) {
                    try {
                        if ($cache->put($key, $item['data'], $item['ttl'])) {
                            $cachedCount++;
                        }
                    } catch (Exception $e) {
                        Log::error('Individual cache operation failed', [
                            'key' => $key,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return $cachedCount;
    }

    /**
     * Factory method for creating the service.
     */
    public static function make(
        ?CachePerformanceServiceInterface $performanceService = null,
        ?Repository $cache = null
    ): self {
        return new self(
            $performanceService ?? CachePerformanceService::make($cache),
            $cache
        );
    }
}
