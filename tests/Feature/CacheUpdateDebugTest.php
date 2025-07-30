<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheUpdateDebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Enable debug mode to see logs
        config(['model-cache.debug_mode_enabled' => true]);
    }

    public function test_debug_cache_update_issue(): void
    {
        echo "\n=== DEBUGGING CACHE UPDATE ISSUE ===\n";

        // Create a model
        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,  // Required for shouldCache() to return true
        ]);
        echo "✅ Created model ID: {$model->id}, Name: '{$model->name}'\n";

        // Check if model should cache
        $shouldCache = $model->shouldCache();
        echo '📋 shouldCache() returns: '.($shouldCache ? 'TRUE' : 'FALSE')."\n";

        // Check if model has primary key
        $primaryKey = $model->getKey();
        echo '📋 Primary key: '.var_export($primaryKey, true)."\n";

        // Check if model exists (is persisted)
        $exists = $model->exists;
        echo '📋 Model exists: '.($exists ? 'TRUE' : 'FALSE')."\n";

        // Check cacheable properties
        $properties = $model->getCacheableProperties();
        echo '📋 Auto invalidate setting: '.($properties['auto_invalidate'] ?? 'NULL')."\n";

        // Cache the model manually first
        $cacheResult = $model->cacheRecord();
        echo '✅ Manual cache result: '.($cacheResult ? 'SUCCESS' : 'FAILED')."\n";

        // Verify it's in cache
        $cachedModel = TestModel::findCached($model->id);
        echo '✅ Found in cache: '.($cachedModel ? "YES (name: '{$cachedModel->name}')" : 'NO')."\n";

        // Capture logs
        $logs = [];
        Log::listen(function ($message) use (&$logs) {
            $logs[] = $message;
        });

        echo "\n--- PERFORMING UPDATE ---\n";

        // Check shouldCache again before update
        $shouldCacheBeforeUpdate = $model->shouldCache();
        echo '📋 shouldCache() before update: '.($shouldCacheBeforeUpdate ? 'TRUE' : 'FALSE')."\n";

        // Update the model
        $updateResult = $model->update(['name' => 'Updated Name']);
        echo '✅ Update result: '.($updateResult ? 'SUCCESS' : 'FAILED')."\n";

        // Check shouldCache after update
        $shouldCacheAfterUpdate = $model->shouldCache();
        echo '📋 shouldCache() after update: '.($shouldCacheAfterUpdate ? 'TRUE' : 'FALSE')."\n";

        // Check model properties after update
        echo '📋 Model ID after update: '.var_export($model->getKey(), true)."\n";
        echo '📋 Model exists after update: '.($model->exists ? 'TRUE' : 'FALSE')."\n";
        echo "📋 Model name after update: '{$model->name}'\n";

        // Check for log entries
        $savedLogs = array_filter($logs, function ($log) {
            return isset($log->message) && str_contains($log->message, 'Model saved and cached');
        });

        echo '📋 Cache update log entries: '.count($savedLogs)."\n";

        if (! empty($savedLogs)) {
            foreach ($savedLogs as $log) {
                echo '📄 Log: '.$log->message."\n";
            }
        }

        // Verify cache was updated
        $updatedCachedModel = TestModel::findCached($model->id);
        if ($updatedCachedModel) {
            echo "✅ Found in cache after update: YES (name: '{$updatedCachedModel->name}')\n";
            $cacheIsUpdated = $updatedCachedModel->name === 'Updated Name';
            echo '✅ Cache is updated: '.($cacheIsUpdated ? 'YES' : 'NO')."\n";
        } else {
            echo "❌ NOT found in cache after update\n";
        }

        echo "\n=== DEBUG COMPLETE ===\n";

        // Assertion to make it a proper test
        $this->assertTrue($model->shouldCache(), 'Model should be cacheable after update');
    }

    public function test_fresh_model_should_cache(): void
    {
        echo "\n=== TESTING FRESH MODEL ===\n";

        // Create model normally
        $model = new TestModel;
        $model->name = 'Test Name';
        $model->email = 'test@example.com';
        $model->is_active = true;  // Required for shouldCache() to return true

        echo '📋 Before save - shouldCache(): '.($model->shouldCache() ? 'TRUE' : 'FALSE')."\n";
        echo '📋 Before save - Primary key: '.var_export($model->getKey(), true)."\n";
        echo '📋 Before save - Exists: '.($model->exists ? 'TRUE' : 'FALSE')."\n";

        $model->save();

        echo '📋 After save - shouldCache(): '.($model->shouldCache() ? 'TRUE' : 'FALSE')."\n";
        echo '📋 After save - Primary key: '.var_export($model->getKey(), true)."\n";
        echo '📋 After save - Exists: '.($model->exists ? 'TRUE' : 'FALSE')."\n";

        echo "\n=== FRESH MODEL TEST COMPLETE ===\n";

        $this->assertTrue($model->shouldCache(), 'Fresh saved model should be cacheable');
    }
}
