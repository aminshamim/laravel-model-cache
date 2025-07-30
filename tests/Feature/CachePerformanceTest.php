<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Services\CachePerformanceService;
use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CachePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_performance_tracking_across_operations(): void
    {
        $model = TestModel::factory()->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Clear any existing cache for this model
        $model->forgetCache();

        // Simulate cache miss (first find) - should not be in cache
        $result1 = TestModel::findCached($model->id);
        $this->assertNotNull($result1);

        // Simulate cache hit (second find) - should be in cache now
        $result2 = TestModel::findCached($model->id);
        $this->assertNotNull($result2);

        // Should have reasonable hit rate (allowing for variance in test environment)
        $hitRate = $performanceService->getHitRate(TestModel::class);
        $this->assertGreaterThan(0.2, $hitRate, 'Hit rate should be greater than 0.2');
        $this->assertLessThan(0.8, $hitRate, 'Hit rate should be less than 0.8');
    }

    public function test_dynamic_ttl_adjustment_in_real_scenario(): void
    {
        $models = TestModel::factory()->count(10)->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Simulate high cache usage (lots of hits)
        foreach ($models as $model) {
            $model->cacheRecord();
            // Multiple hits per model
            TestModel::findCached($model->id);
            TestModel::findCached($model->id);
            TestModel::findCached($model->id);
        }

        $hitRate = $performanceService->getHitRate(TestModel::class);
        $this->assertGreaterThan(0.5, $hitRate);

        $dynamicTTL = $performanceService->getDynamicTTL(TestModel::class, 300);
        $this->assertGreaterThan(300, $dynamicTTL);
    }

    public function test_performance_stats_persistence(): void
    {
        $performanceService = CachePerformanceService::make();

        // Record some stats
        $performanceService->recordHit(TestModel::class);
        $performanceService->recordMiss(TestModel::class);

        // Create new service instance (simulating new request)
        $newService = CachePerformanceService::make();

        $hitRate = $newService->getHitRate(TestModel::class);
        $this->assertEquals(0.5, $hitRate);
    }

    public function test_bulk_operations_performance(): void
    {
        $models = TestModel::factory()->count(50)->create();
        $ids = $models->pluck('id')->toArray();

        // Measure time for bulk cache operation
        $startTime = microtime(true);

        $results = TestModel::findManyCached($ids);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertCount(50, $results);
        // Should complete reasonably quickly (less than 1 second for this small dataset)
        $this->assertLessThan(1.0, $executionTime);
    }

    public function test_cache_efficiency_with_repeated_access(): void
    {
        $model = TestModel::factory()->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // First access - should be a miss and then cache
        $result1 = TestModel::findCached($model->id);
        $this->assertNotNull($result1);

        // Multiple subsequent accesses - should be hits
        for ($i = 0; $i < 10; $i++) {
            $result = TestModel::findCached($model->id);
            $this->assertNotNull($result);
        }

        $hitRate = $performanceService->getHitRate(TestModel::class);
        // Should have high hit rate (10 hits out of 11 total)
        $this->assertGreaterThan(0.9, $hitRate);
    }

    public function test_performance_with_model_updates(): void
    {
        $model = TestModel::factory()->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Cache the model
        $result1 = TestModel::findCached($model->id);
        $this->assertNotNull($result1);

        // Update the model (should refresh cache)
        $model->update(['name' => 'Updated Name']);

        // Find again (should hit cache with updated data)
        $result2 = TestModel::findCached($model->id);
        $this->assertEquals('Updated Name', $result2->name);

        // Performance should still be good
        $hitRate = $performanceService->getHitRate(TestModel::class);
        $this->assertGreaterThan(0.0, $hitRate);
    }

    public function test_memory_usage_with_large_dataset(): void
    {
        $initialMemory = memory_get_usage();

        // Create and cache many models
        $models = TestModel::factory()->count(100)->create();

        foreach ($models as $model) {
            $model->cacheRecord();
        }

        $afterCachingMemory = memory_get_usage();
        $memoryIncrease = $afterCachingMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB for 100 models)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
    }

    public function test_concurrent_access_simulation(): void
    {
        $model = TestModel::factory()->create();
        $performanceService = CachePerformanceService::make();

        // Reset stats
        $performanceService->resetStats(TestModel::class);

        // Simulate concurrent access by multiple "users"
        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = TestModel::findCached($model->id);
        }

        // All results should be the same model
        foreach ($results as $result) {
            $this->assertNotNull($result);
            $this->assertEquals($model->id, $result->id);
        }

        // Should have good hit rate
        $hitRate = $performanceService->getHitRate(TestModel::class);
        $this->assertGreaterThan(0.9, $hitRate);
    }
}
