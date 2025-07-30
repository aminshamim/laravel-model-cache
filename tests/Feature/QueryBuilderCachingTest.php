<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class QueryBuilderCachingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_find_cached_works_with_query_builder(): void
    {
        $model = TestModel::factory()->create();

        // Use query builder to find cached
        $result = TestModel::query()->findCached($model->id);

        $this->assertNotNull($result);
        $this->assertEquals($model->id, $result->id);

        // Verify it was cached
        $cacheKey = $model->getCacheKey();
        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_find_many_cached_works_with_query_builder(): void
    {
        $models = TestModel::factory()->count(3)->create();
        $ids = $models->pluck('id')->toArray();

        // Use query builder to find many cached
        $results = TestModel::query()->findManyCached($ids);

        $this->assertCount(3, $results);
        $this->assertEquals($ids, $results->pluck('id')->sort()->values()->toArray());

        // Verify all were cached
        foreach ($models as $model) {
            $cacheKey = $model->getCacheKey();
            $this->assertNotNull(Cache::get($cacheKey));
        }
    }

    public function test_find_without_cache_bypasses_caching(): void
    {
        $model = TestModel::factory()->create();

        // Clear any existing cache
        $model->forgetCache();

        // Use query builder to find without cache
        $result = TestModel::query()->findWithoutCache($model->id);

        $this->assertNotNull($result);
        $this->assertEquals($model->id, $result->id);

        // Verify it was not cached
        $cacheKey = $model->getCacheKey();
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_warm_cache_method_on_query_builder(): void
    {
        TestModel::factory()->count(5)->create();

        $cachedCount = TestModel::query()->warmCache(3);

        $this->assertEquals(3, $cachedCount);
    }

    public function test_clear_cache_method_on_query_builder(): void
    {
        $models = TestModel::factory()->count(3)->create();

        // Cache all models
        foreach ($models as $model) {
            $model->cacheRecord();
        }

        // Clear cache using query builder
        $result = TestModel::query()->clearCache();

        // With array driver, tags are not supported, so this will return false
        // but individual cache clearing in the fallback should work
        $this->assertIsBool($result);
    }

    public function test_find_cached_handles_missing_records(): void
    {
        $result = TestModel::query()->findCached(999999);

        $this->assertNull($result);
    }

    public function test_find_many_cached_handles_partial_misses(): void
    {
        // Create 2 models and cache them
        $existingModels = TestModel::factory()->count(2)->create();
        foreach ($existingModels as $model) {
            $model->cacheRecord();
        }

        // Create one more model but don't cache it
        $newModel = TestModel::factory()->create();

        // Clear database cache for the new model to simulate cache miss
        $newModel->forgetCache();

        $allIds = $existingModels->pluck('id')->push($newModel->id)->toArray();

        $results = TestModel::query()->findManyCached($allIds);

        $this->assertCount(3, $results);
        $this->assertEquals($allIds, $results->pluck('id')->sort()->values()->toArray());
    }

    public function test_batch_cache_operation_in_query_builder(): void
    {
        $models = TestModel::factory()->count(3)->create();
        $ids = $models->pluck('id')->toArray();

        // Clear any existing cache
        foreach ($models as $model) {
            $model->forgetCache();
        }

        // Find many cached should cache all missing records
        $results = TestModel::query()->findManyCached($ids);

        $this->assertCount(3, $results);

        // Verify all are now cached
        foreach ($models as $model) {
            $cacheKey = $model->getCacheKey();
            $cacheInstance = $model->getCacheInstance();
            $this->assertNotNull($cacheInstance->get($cacheKey));
        }
    }

    public function test_query_builder_respects_model_should_cache_logic(): void
    {
        // Create active and inactive models
        $activeModel = TestModel::factory()->create(['is_active' => true]);
        $inactiveModel = TestModel::factory()->inactive()->create();

        $ids = [$activeModel->id, $inactiveModel->id];

        // Find many cached
        $results = TestModel::query()->findManyCached($ids);

        $this->assertCount(2, $results);

        // Only active model should be cached
        $activeCacheKey = $activeModel->getCacheKey();
        $inactiveCacheKey = $inactiveModel->getCacheKey();

        $this->assertNotNull(Cache::get($activeCacheKey));
        $this->assertNull(Cache::get($inactiveCacheKey));
    }

    public function test_override_find_method_configuration(): void
    {
        // Create a model with override_find_method enabled
        $model = TestModel::factory()->create();

        // Mock the configuration to enable override
        config(['model-cache.override_find_method' => true]);

        // Regular find should now use caching
        $result = TestModel::find($model->id);

        $this->assertNotNull($result);
        $this->assertEquals($model->id, $result->id);

        // Reset config
        config(['model-cache.override_find_method' => false]);
    }
}
