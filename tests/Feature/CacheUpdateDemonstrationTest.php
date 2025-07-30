<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Tests\Feature;

use AminShamim\LaravelModelCache\Tests\Models\TestModel;
use AminShamim\LaravelModelCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class CacheUpdateDemonstrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_demonstrate_cache_update_working_correctly(): void
    {
        echo "\n=== Demonstrating Cache Update Functionality ===\n";

        // 1. Create a model
        $model = TestModel::factory()->create([
            'name' => 'Original Name',
            'is_active' => true,
        ]);
        echo "✅ Created model with name: '{$model->name}'\n";

        // 2. Cache the model
        $result = $model->cacheRecord();
        $this->assertTrue($result);
        echo "✅ Model cached successfully\n";

        // 3. Verify it's in cache
        $cachedModel = TestModel::findCached($model->id);
        $this->assertEquals('Original Name', $cachedModel->name);
        echo "✅ Found in cache with name: '{$cachedModel->name}'\n";

        // 4. Update the model - this should automatically update cache due to auto_invalidate
        $model->update(['name' => 'Updated Name']);
        echo "✅ Updated model name to: 'Updated Name'\n";

        // 5. Verify cache is automatically updated
        $updatedCachedModel = TestModel::findCached($model->id);
        $this->assertEquals('Updated Name', $updatedCachedModel->name);
        echo "✅ Cache automatically updated! Found in cache with name: '{$updatedCachedModel->name}'\n";

        // 6. Test with auto_invalidate disabled
        config(['model-cache.auto_invalidate' => false]);
        echo "\n--- Testing with auto_invalidate disabled ---\n";

        $model2 = TestModel::factory()->create([
            'name' => 'Test Name',
            'is_active' => true,
        ]);
        $model2->cacheRecord();
        echo "✅ Created and cached second model with name: '{$model2->name}'\n";

        // Update model - cache should NOT be automatically updated
        $model2->update(['name' => 'Modified Name']);
        echo "✅ Updated second model name to: 'Modified Name'\n";

        $staleCachedModel = TestModel::findCached($model2->id);
        $this->assertEquals('Test Name', $staleCachedModel->name);
        echo "✅ Cache correctly NOT updated automatically! Still has old name: '{$staleCachedModel->name}'\n";

        // Manual cache update should work
        $model2->refresh();
        $model2->cacheRecord();

        $freshCachedModel = TestModel::findCached($model2->id);
        $this->assertEquals('Modified Name', $freshCachedModel->name);
        echo "✅ Manual cache update worked! Now has name: '{$freshCachedModel->name}'\n";

        // Reset config
        config(['model-cache.auto_invalidate' => true]);

        echo "\n=== Cache Update Functionality Working Correctly! ===\n";
    }
}
