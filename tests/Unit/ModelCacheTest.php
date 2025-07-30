<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Unit;

use AminShamim\LaravelModelCache\Services\CachePerformanceService;
use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class ModelCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_model_can_be_cached(): void
    {
        $model = TestModel::factory()->create();

        $this->assertNotNull($model->getKey(), 'Model should have a primary key');
        $this->assertTrue($model->shouldCache(), 'Model should be cacheable');

        $result = $model->cacheRecord();
        $this->assertTrue($result, 'Cache record should return true');

        $cacheKey = $model->getCacheKey();
        $this->assertNotEmpty($cacheKey, 'Cache key should not be empty');

        // Verify the data is in cache
        $cacheInstance = $model->getCacheInstance();
        $cachedData = $cacheInstance->get($cacheKey);

        $this->assertNotNull($cachedData, 'Cache should contain data for key: '.$cacheKey);
        $this->assertIsArray($cachedData, 'Cached data should be an array');
        $this->assertArrayHasKey('attributes', $cachedData, 'Cached data should have attributes');
        $this->assertEquals($model->id, $cachedData['attributes']['id'], 'Cached ID should match model ID');
    }

    public function test_model_can_be_found_from_cache(): void
    {
        $model = TestModel::factory()->create();

        // Cache the model
        $model->cacheRecord();

        // Find from cache
        $cachedModel = TestModel::findCached($model->id);

        $this->assertNotNull($cachedModel);
        $this->assertEquals($model->id, $cachedModel->id);
        $this->assertEquals($model->name, $cachedModel->name);
        $this->assertEquals($model->email, $cachedModel->email);
    }

    public function test_cache_is_cleared_when_model_is_deleted(): void
    {
        $model = TestModel::factory()->create();

        // Cache the model
        $model->cacheRecord();
        $cacheKey = $model->getCacheKey();

        $this->assertNotNull(Cache::get($cacheKey));

        // Delete the model
        $model->delete();

        // Cache should be cleared
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_cache_is_updated_when_model_is_saved(): void
    {
        $model = TestModel::factory()->create();

        // Cache the model
        $model->cacheRecord();

        // Update the model
        $newName = 'Updated Name';
        $model->update(['name' => $newName]);

        // Find from cache
        $cachedModel = TestModel::findCached($model->id);

        $this->assertEquals($newName, $cachedModel->name);
    }

    public function test_multiple_models_can_be_found_with_caching(): void
    {
        $models = TestModel::factory()->count(3)->create();
        $ids = $models->pluck('id')->toArray();

        // Cache all models
        foreach ($models as $model) {
            $model->cacheRecord();
        }

        // Find multiple from cache
        $cachedModels = TestModel::findManyCached($ids);

        $this->assertCount(3, $cachedModels);
        $this->assertEquals($ids, $cachedModels->pluck('id')->sort()->values()->toArray());
    }

    public function test_inactive_models_are_not_cached(): void
    {
        $model = TestModel::factory()->inactive()->create();

        $result = $model->cacheRecord();

        $this->assertFalse($result);

        $cacheKey = $model->getCacheKey();
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_cache_key_generation(): void
    {
        $model = TestModel::factory()->create();

        $cacheKey = $model->getCacheKey();

        $this->assertStringContainsString('model-cache', $cacheKey);
        $this->assertStringContainsString((string) $model->id, $cacheKey);
    }

    public function test_cache_statistics_tracking(): void
    {
        $model = TestModel::factory()->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Record some hits and misses
        $performanceService->recordHit(TestModel::class);
        $performanceService->recordHit(TestModel::class);
        $performanceService->recordMiss(TestModel::class);

        $hitRate = $performanceService->getHitRate(TestModel::class);

        $this->assertEquals(0.6667, round($hitRate, 4)); // 2 hits out of 3 total
    }

    public function test_dynamic_ttl_adjustment(): void
    {
        $performanceService = CachePerformanceService::make();
        $defaultTTL = 300;

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // High hit rate should increase TTL
        for ($i = 0; $i < 10; $i++) {
            $performanceService->recordHit(TestModel::class);
        }
        $performanceService->recordMiss(TestModel::class);

        $ttl = $performanceService->getDynamicTTL(TestModel::class, $defaultTTL);
        $this->assertGreaterThan($defaultTTL, $ttl);

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Low hit rate should decrease TTL
        for ($i = 0; $i < 10; $i++) {
            $performanceService->recordMiss(TestModel::class);
        }
        $performanceService->recordHit(TestModel::class);

        $ttl = $performanceService->getDynamicTTL(TestModel::class, $defaultTTL);
        $this->assertLessThan($defaultTTL, $ttl);
    }

    public function test_cache_tags_are_generated(): void
    {
        $model = TestModel::factory()->create();

        $tags = $model->getCacheTags();

        $this->assertIsArray($tags);
        $this->assertNotEmpty($tags);
        $this->assertContains('model-cache', $tags);
        $this->assertContains('model-cache:all', $tags);
    }

    public function test_cache_can_be_forgotten(): void
    {
        $model = TestModel::factory()->create();

        // Cache the model
        $model->cacheRecord();
        $cacheKey = $model->getCacheKey();

        $this->assertNotNull(Cache::get($cacheKey));

        // Forget cache
        $result = $model->forgetCache();

        $this->assertTrue($result);
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_find_cached_returns_null_for_non_existent_id(): void
    {
        $result = TestModel::findCached(999999);

        $this->assertNull($result);
    }

    public function test_find_many_cached_returns_empty_collection_for_empty_array(): void
    {
        $result = TestModel::findManyCached([]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_custom_cacheable_properties_are_applied(): void
    {
        $model = TestModel::factory()->create();
        $properties = $model->getCacheableProperties();

        // TestModel overrides TTL to 600
        $this->assertEquals(600, $properties['ttl']);
        $this->assertTrue($properties['cache_relationships']);
    }

    public function test_cache_data_structure(): void
    {
        $model = TestModel::factory()->create();

        $cacheData = $model->prepareCacheData();

        $this->assertIsArray($cacheData);
        $this->assertArrayHasKey('attributes', $cacheData);
        $this->assertArrayHasKey('original', $cacheData);
        $this->assertArrayHasKey('relations', $cacheData);
        $this->assertArrayHasKey('cached_at', $cacheData);
        $this->assertArrayHasKey('cache_version', $cacheData);
    }

    public function test_model_can_be_created_from_cache_data(): void
    {
        $model = TestModel::factory()->create();
        $cacheData = $model->prepareCacheData();

        $restoredModel = TestModel::createFromCacheData($cacheData);

        $this->assertInstanceOf(TestModel::class, $restoredModel);
        $this->assertEquals($model->id, $restoredModel->id);
        $this->assertEquals($model->name, $restoredModel->name);
        $this->assertEquals($model->email, $restoredModel->email);
    }

    public function test_soft_deleted_models_are_not_cached(): void
    {
        $model = TestModel::factory()->create();

        // Soft delete the model
        $model->delete();

        $result = $model->cacheRecord();

        $this->assertFalse($result);
    }

    public function test_cache_statistics_can_be_retrieved(): void
    {
        $model = TestModel::factory()->create();

        $stats = $model->getCacheStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
    }

    public function test_performance_service_tracks_all_stats(): void
    {
        $performanceService = CachePerformanceService::make();

        // Test with different model classes
        $performanceService->recordHit('Model1');
        $performanceService->recordMiss('Model1');
        $performanceService->recordHit('Model2');

        $hitRate1 = $performanceService->getHitRate('Model1');
        $hitRate2 = $performanceService->getHitRate('Model2');

        $this->assertEquals(0.5, $hitRate1); // 1 hit out of 2 total
        $this->assertEquals(1.0, $hitRate2); // 1 hit out of 1 total
    }
}
