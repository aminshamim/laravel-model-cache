<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CacheUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_cache_is_properly_updated_when_model_is_updated(): void
    {
        // Create a model
        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        // Cache the model manually first
        $result = $model->cacheRecord();
        $this->assertTrue($result, 'Model should be cached successfully');

        // Verify it's in cache with original name
        $cachedModel = TestModel::findCached($model->id);
        $this->assertNotNull($cachedModel, 'Model should be found in cache');
        $this->assertEquals('Original Name', $cachedModel->name, 'Cached model should have original name');

        // Update the model - this should trigger the saved event and update cache
        $model->update(['name' => 'Updated Name']);

        // Clear the model instance to ensure we're getting fresh data
        $model->refresh();

        // Find from cache again - should have updated data
        $updatedCachedModel = TestModel::findCached($model->id);
        $this->assertNotNull($updatedCachedModel, 'Updated model should be found in cache');
        $this->assertEquals('Updated Name', $updatedCachedModel->name, 'Cached model should have updated name');

        // Verify the cache key still exists and has correct data
        $cacheKey = $model->getCacheKey();
        $cacheInstance = $model->getCacheInstance();
        $rawCachedData = $cacheInstance->get($cacheKey);

        $this->assertNotNull($rawCachedData, 'Cache should still contain data after update');
        $this->assertIsArray($rawCachedData, 'Cached data should be an array');
        $this->assertEquals('Updated Name', $rawCachedData['attributes']['name'], 'Raw cached data should have updated name');
    }

    public function test_cache_invalidation_with_auto_invalidate_disabled(): void
    {
        // Temporarily disable auto_invalidate globally
        config(['model-cache.auto_invalidate' => false]);

        // Create a regular model
        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        // Cache the model manually
        $model->cacheRecord();

        // Verify it's in cache
        $cachedModel = TestModel::findCached($model->id);
        $this->assertEquals('Original Name', $cachedModel->name);

        // Update the model - cache should NOT be automatically updated
        $model->update(['name' => 'Updated Name']);

        // Cache should still have old data because auto_invalidate is false
        $staleModel = TestModel::findCached($model->id);
        $this->assertEquals('Original Name', $staleModel->name, 'Cache should still have old data when auto_invalidate is false');

        // Manual cache update should work
        $model->refresh();
        $model->cacheRecord();

        $freshModel = TestModel::findCached($model->id);
        $this->assertEquals('Updated Name', $freshModel->name, 'Manual cache update should work');

        // Reset auto_invalidate
        config(['model-cache.auto_invalidate' => true]);
    }

    public function test_multiple_updates_keep_cache_in_sync(): void
    {
        $model = TestModel::factory()->create([
            'name' => 'Name 1',
            'is_active' => true,
        ]);

        // Cache the model
        $model->cacheRecord();

        $updates = ['Name 2', 'Name 3', 'Name 4', 'Final Name'];

        foreach ($updates as $index => $newName) {
            // Update the model
            $model->update(['name' => $newName]);

            // Verify cache is updated
            $cachedModel = TestModel::findCached($model->id);
            $this->assertEquals($newName, $cachedModel->name, "Cache should have name '{$newName}' after update ".($index + 1));
        }
    }

    public function test_cache_update_preserves_other_attributes(): void
    {
        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
            'is_active' => true,
        ]);

        // Cache the model
        $model->cacheRecord();

        // Update only the name
        $model->update(['name' => 'Updated Name']);

        // Verify both attributes are correct in cache
        $cachedModel = TestModel::findCached($model->id);
        $this->assertEquals('Updated Name', $cachedModel->name, 'Name should be updated');
        $this->assertEquals('original@test.com', $cachedModel->email, 'Email should be preserved');
    }

    public function test_debug_logging_for_cache_updates(): void
    {
        // Enable debug mode
        config(['model-cache.debug_mode_enabled' => true]);

        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);

        // Cache the model
        $model->cacheRecord();

        // Capture logs
        $logs = [];
        \Log::listen(function ($message) use (&$logs) {
            $logs[] = $message;
        });

        // Update the model - this should trigger debug logging
        $model->update(['name' => 'Updated Name']);

        // Verify we have appropriate log entries
        $savedLogs = array_filter($logs, function ($log) {
            return strpos($log->message ?? '', 'Model saved and cached') !== false;
        });

        $this->assertNotEmpty($savedLogs, 'Should have logged the model save/cache event');

        // Reset debug mode
        config(['model-cache.debug_mode_enabled' => false]);
    }
}
