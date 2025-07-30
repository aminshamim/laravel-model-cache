<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Facades\ModelCache;
use AminShamim\LaravelModelCache\Services\ModelCacheService;
use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ModelCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_warm_cache_for_all_records(): void
    {
        $models = TestModel::factory()->count(5)->create();

        $service = ModelCacheService::make();
        $cachedCount = $service->warmCache(TestModel::class);

        $this->assertEquals(5, $cachedCount);

        // Verify all models are cached
        foreach ($models as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNotNull(Cache::get($cacheKey));
        }
    }

    public function test_warm_cache_for_specific_ids(): void
    {
        $models = TestModel::factory()->count(5)->create();

        // Clear any existing cache
        foreach ($models as $model) {
            $model->forgetCache();
        }

        $idsToCache = $models->take(3)->pluck('id')->toArray();

        $service = ModelCacheService::make();
        $cachedCount = $service->warmCache(TestModel::class, $idsToCache);

        $this->assertEquals(3, $cachedCount);

        // Verify only specified models are cached
        foreach ($models->take(3) as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNotNull(Cache::get($cacheKey));
        }

        foreach ($models->skip(3) as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNull(Cache::get($cacheKey));
        }
    }

    public function test_get_cache_stats(): void
    {
        $service = ModelCacheService::make();
        $stats = $service->getCacheStats(TestModel::class);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('model_class', $stats);
        $this->assertArrayHasKey('timestamp', $stats);
        $this->assertEquals(TestModel::class, $stats['model_class']);
    }

    public function test_clear_cache_for_specific_records(): void
    {
        $models = TestModel::factory()->count(3)->create();

        // Cache all models
        foreach ($models as $model) {
            $model->cacheRecord();
        }

        // Clear cache for first two models
        $service = ModelCacheService::make();
        $idsTolear = $models->take(2)->pluck('id')->toArray();
        $result = $service->clearCacheForRecords(TestModel::class, $idsTolear);

        $this->assertTrue($result);

        // Verify cache is cleared for specified models
        foreach ($models->take(2) as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNull(Cache::get($cacheKey));
        }

        // Verify cache still exists for the third model
        $thirdModel = $models->skip(2)->first();
        $cacheKey = $thirdModel->getCacheKey();
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_batch_cache_multiple_models(): void
    {
        $models = TestModel::factory()->count(3)->create();

        $service = ModelCacheService::make();
        $cachedCount = $service->batchCache($models);

        $this->assertEquals(3, $cachedCount);

        // Verify all models are cached
        foreach ($models as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNotNull(Cache::get($cacheKey));
        }
    }

    public function test_optimize_cache_with_no_limit(): void
    {
        $service = ModelCacheService::make();
        $result = $service->optimizeCache(TestModel::class);

        $this->assertIsArray($result);
        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals('No limit configured', $result['reason']);
    }

    public function test_facade_works(): void
    {
        $models = TestModel::factory()->count(2)->create();

        $cachedCount = ModelCache::warmCache(TestModel::class);

        $this->assertEquals(2, $cachedCount);
    }

    public function test_service_handles_invalid_model_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = ModelCacheService::make();
        $service->warmCache('InvalidClass');
    }

    public function test_batch_cache_excludes_inactive_models(): void
    {
        $activeModels = TestModel::factory()->count(2)->create();
        $inactiveModels = TestModel::factory()->inactive()->count(2)->create();

        $allModels = $activeModels->merge($inactiveModels);

        $service = ModelCacheService::make();
        $cachedCount = $service->batchCache($allModels);

        // Only active models should be cached (based on shouldCache() logic)
        $this->assertEquals(2, $cachedCount);

        // Verify active models are cached
        foreach ($activeModels as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNotNull(Cache::get($cacheKey));
        }

        // Verify inactive models are not cached
        foreach ($inactiveModels as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNull(Cache::get($cacheKey));
        }
    }

    public function test_cache_instance_can_be_retrieved(): void
    {
        $service = ModelCacheService::make();
        $cache = $service->getCacheInstance();

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Repository::class, $cache);
    }

    public function test_cache_instance_can_use_custom_driver(): void
    {
        $service = ModelCacheService::make();
        $cache = $service->getCacheInstance('array');

        $this->assertInstanceOf(\Illuminate\Contracts\Cache\Repository::class, $cache);
    }
}
