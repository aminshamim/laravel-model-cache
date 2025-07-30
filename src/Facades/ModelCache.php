<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Facades;

use AminShamim\LaravelModelCache\Contracts\ModelCacheServiceInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static int warmCache(string $modelClass, array $ids = [])
 * @method static array getCacheStats(string $modelClass)
 * @method static bool clearCacheForRecords(string $modelClass, array $ids)
 * @method static array optimizeCache(string $modelClass)
 * @method static \Illuminate\Contracts\Cache\Repository getCacheInstance(?string $driver = null)
 * @method static int batchCache(\Illuminate\Support\Collection $models)
 */
class ModelCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModelCacheServiceInterface::class;
    }
}
