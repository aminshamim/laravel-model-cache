# Usage Examples

## Overview

This document provides comprehensive usage examples for the Laravel Model Cache package, covering basic usage, advanced scenarios, and real-world implementation patterns.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Model Configuration](#model-configuration)
- [Advanced Caching Patterns](#advanced-caching-patterns)
- [Real-World Examples](#real-world-examples)
- [Integration Examples](#integration-examples)
- [Testing Examples](#testing-examples)
- [Troubleshooting Examples](#troubleshooting-examples)

## Basic Usage

### Simple Model Caching

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use ModelCacheable;

    protected $fillable = ['name', 'email', 'status'];
}

// Basic usage examples
$user = User::findCached(1);                    // Find with cache
$users = User::findManyCached([1, 2, 3, 4, 5]); // Find multiple with cache

// Manual cache management
$user = User::find(1);
$user->cacheRecord();    // Cache manually
$user->forgetCache();    // Remove from cache
User::forgetAllCache();  // Clear all user cache
```

### Quick Setup for Existing Models

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use ModelCacheable;

    // That's it! Your model now has caching capabilities
    protected $fillable = ['name', 'price', 'category_id'];
}

// Usage
$product = Product::findCached(1);
$products = Product::findManyCached([1, 2, 3]);
```

## Model Configuration

### Custom Cache Configuration

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use ModelCacheable;

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => 600,                    // Cache for 10 minutes
            'prefix' => 'users',             // Custom cache prefix
            'auto_invalidate' => true,       // Auto-clear on updates
            'override_find_method' => false, // Don't override find()
            'logging' => [
                'enabled' => true,
                'channel' => 'cache',
                'level' => 'info',
            ],
            'driver' => 'redis',            // Use Redis driver
        ];
    }
}
```

### Environment-Based Configuration

```php
public function getCacheableProperties(): array
{
    $config = [
        'auto_invalidate' => true,
        'prefix' => 'users',
    ];

    // Different settings per environment
    if (app()->environment('production')) {
        $config['ttl'] = 1800;        // 30 minutes in production
        $config['driver'] = 'redis';
        $config['logging']['enabled'] = false;
    } elseif (app()->environment('testing')) {
        $config['ttl'] = 30;          // 30 seconds in testing
        $config['driver'] = 'array';
    } else {
        $config['ttl'] = 300;         // 5 minutes in development
        $config['logging']['enabled'] = true;
    }

    return $config;
}
```

### Model-Specific Optimization

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use ModelCacheable;

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => 3600,               // Categories rarely change
            'prefix' => 'categories',
            'auto_invalidate' => true,
            'max_records_per_model' => 100, // Limit cache size
        ];
    }

    // Don't cache inactive categories
    protected function shouldCache(): bool
    {
        return $this->is_active && parent::shouldCache();
    }
}
```

## Advanced Caching Patterns

### Conditional Caching

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use ModelCacheable;

    protected function shouldCache(): bool
    {
        // Only cache completed orders
        if ($this->status !== 'completed') {
            return false;
        }

        // Don't cache recent orders (they might still change)
        if ($this->created_at && $this->created_at->gt(now()->subHours(2))) {
            return false;
        }

        return parent::shouldCache();
    }

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => $this->getOptimalTTL(),
            'prefix' => 'orders',
            'auto_invalidate' => true,
        ];
    }

    private function getOptimalTTL(): int
    {
        // Longer cache for older orders
        if ($this->created_at && $this->created_at->lt(now()->subMonths(6))) {
            return 7200; // 2 hours for old orders
        }

        return 600; // 10 minutes for recent orders
    }
}
```

### Context-Aware Caching

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use ModelCacheable;

    protected function getCacheKey(): string
    {
        $baseKey = parent::getCacheKey();
        
        // Include user role in cache key
        $userRole = auth()->user()?->role ?? 'guest';
        
        // Include language preference
        $language = app()->getLocale();
        
        return "{$baseKey}:{$userRole}:{$language}";
    }

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => auth()->check() ? 900 : 300, // Longer for authenticated users
            'prefix' => 'user-profiles',
            'auto_invalidate' => true,
        ];
    }
}
```

### Batch Operations

```php
<?php

namespace App\Services;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;

class UserService
{
    public function getActiveUsers(array $userIds): Collection
    {
        // Try to get all from cache first
        $cachedUsers = collect();
        $missingIds = [];

        foreach ($userIds as $id) {
            $user = User::findCached($id);
            if ($user && $user->is_active) {
                $cachedUsers->push($user);
            } else {
                $missingIds[] = $id;
            }
        }

        // Fetch missing users from database
        if (!empty($missingIds)) {
            $dbUsers = User::whereIn('id', $missingIds)
                ->where('is_active', true)
                ->get();

            // Cache the fetched users
            $dbUsers->each(function ($user) {
                $user->cacheRecord();
            });

            $cachedUsers = $cachedUsers->merge($dbUsers);
        }

        return $cachedUsers;
    }

    public function warmPopularUsers(): int
    {
        // Get most popular user IDs (from analytics, etc.)
        $popularIds = $this->getPopularUserIds();
        
        return ModelCacheService::warmCache(User::class, $popularIds);
    }

    private function getPopularUserIds(): array
    {
        // Implementation depends on your analytics
        return User::select('id')
            ->where('last_login_at', '>', now()->subDays(7))
            ->orderBy('login_count', 'desc')
            ->limit(1000)
            ->pluck('id')
            ->toArray();
    }
}
```

## Real-World Examples

### E-commerce Product Catalog

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use ModelCacheable;

    protected $fillable = ['name', 'price', 'category_id', 'is_featured', 'stock'];

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => $this->getCacheTTL(),
            'prefix' => 'products',
            'auto_invalidate' => true,
            'max_records_per_model' => 10000,
        ];
    }

    private function getCacheTTL(): int
    {
        // Featured products cache longer
        if ($this->is_featured) {
            return 1800; // 30 minutes
        }

        // Products with high stock cache longer
        if ($this->stock > 100) {
            return 900; // 15 minutes
        }

        // Low stock products cache briefly
        return 300; // 5 minutes
    }

    protected function shouldCache(): bool
    {
        // Don't cache out-of-stock products
        return $this->stock > 0 && parent::shouldCache();
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

// Usage in a service
class ProductService
{
    public function getFeaturedProducts(int $limit = 10): Collection
    {
        $featuredIds = Cache::remember('featured_product_ids', 600, function () use ($limit) {
            return Product::where('is_featured', true)
                ->where('stock', '>', 0)
                ->orderBy('featured_priority')
                ->limit($limit)
                ->pluck('id')
                ->toArray();
        });

        return Product::findManyCached($featuredIds);
    }

    public function getProductsByCategory(int $categoryId): Collection
    {
        $cacheKey = "category_products:{$categoryId}";
        
        $productIds = Cache::remember($cacheKey, 300, function () use ($categoryId) {
            return Product::where('category_id', $categoryId)
                ->where('stock', '>', 0)
                ->pluck('id')
                ->toArray();
        });

        return Product::findManyCached($productIds);
    }
}
```

### User Management System

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use ModelCacheable;

    protected $fillable = ['name', 'email', 'role', 'is_active', 'last_login_at'];

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => $this->getUserCacheTTL(),
            'prefix' => 'users',
            'auto_invalidate' => true,
            'logging' => [
                'enabled' => config('app.debug'),
                'level' => 'info',
            ],
        ];
    }

    private function getUserCacheTTL(): int
    {
        // VIP users cache longer
        if ($this->role === 'vip') {
            return 1800; // 30 minutes
        }

        // Active users cache longer
        if ($this->last_login_at && $this->last_login_at->gt(now()->subDays(7))) {
            return 900; // 15 minutes
        }

        return 300; // 5 minutes for others
    }

    protected function shouldCache(): bool
    {
        // Only cache active users
        return $this->is_active && parent::shouldCache();
    }

    // Custom cache key including role
    protected function getCacheKey(): string
    {
        $baseKey = parent::getCacheKey();
        return "{$baseKey}:role:{$this->role}";
    }
}

// Usage in authentication
class AuthService
{
    public function getUserById(int $id): ?User
    {
        return User::findCached($id);
    }

    public function getAdminUsers(): Collection
    {
        $adminIds = Cache::remember('admin_user_ids', 600, function () {
            return User::where('role', 'admin')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        });

        return User::findManyCached($adminIds);
    }
}
```

### Content Management

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use ModelCacheable;

    protected $fillable = ['title', 'content', 'is_published', 'published_at'];

    public function getCacheableProperties(): array
    {
        return [
            'ttl' => $this->getContentCacheTTL(),
            'prefix' => 'articles',
            'auto_invalidate' => true,
        ];
    }

    private function getContentCacheTTL(): int
    {
        // Published articles cache longer
        if ($this->is_published && $this->published_at) {
            // Older articles cache much longer
            if ($this->published_at->lt(now()->subMonths(3))) {
                return 7200; // 2 hours
            }
            
            return 1800; // 30 minutes
        }

        return 300; // 5 minutes for drafts
    }

    protected function shouldCache(): bool
    {
        // Only cache published articles or recent drafts
        if ($this->is_published) {
            return true;
        }

        // Cache recent drafts
        return $this->updated_at && 
               $this->updated_at->gt(now()->subHours(24)) && 
               parent::shouldCache();
    }
}

// Usage in CMS
class ArticleService
{
    public function getPublishedArticles(int $limit = 20): Collection
    {
        $articleIds = Cache::remember('published_article_ids', 600, function () use ($limit) {
            return Article::where('is_published', true)
                ->orderBy('published_at', 'desc')
                ->limit($limit)
                ->pluck('id')
                ->toArray();
        });

        return Article::findManyCached($articleIds);
    }

    public function getArticleBySlug(string $slug): ?Article
    {
        // First try to get article ID from slug cache
        $articleId = Cache::remember("article_slug:{$slug}", 1800, function () use ($slug) {
            return Article::where('slug', $slug)
                ->where('is_published', true)
                ->value('id');
        });

        return $articleId ? Article::findCached($articleId) : null;
    }
}
```

## Integration Examples

### Laravel Nova Integration

```php
<?php

namespace App\Nova;

use Laravel\Nova\Resource;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use AminShamim\ModelCache\Services\ModelCacheService;

class User extends Resource
{
    public static $model = \App\Models\User::class;

    public function fields(Request $request)
    {
        return [
            Text::make('Name'),
            Text::make('Email'),
            Boolean::make('Is Cached', function () {
                return $this->getCacheInfo()['is_cached'] ?? false;
            }),
            Text::make('Cache Hit Rate', function () {
                $stats = ModelCacheService::getCacheStats(static::$model);
                return number_format($stats['cache_hit_rate'] * 100, 2) . '%';
            }),
        ];
    }

    public function actions(Request $request)
    {
        return [
            new Actions\ClearUserCache,
            new Actions\WarmUserCache,
        ];
    }
}

// Nova Actions
class ClearUserCache extends Action
{
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            $model->forgetCache();
        }

        return Action::message('Cache cleared for selected users');
    }
}
```

### API Resource Integration

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_cached' => $this->getCacheInfo()['is_cached'] ?? false,
            'cache_ttl_remaining' => $this->getCacheInfo()['ttl_remaining'] ?? null,
        ];
    }
}

// Usage in controller
class UserController extends Controller
{
    public function show(int $id)
    {
        $user = User::findCached($id);
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return new UserResource($user);
    }

    public function index(Request $request)
    {
        $userIds = $request->input('ids', []);
        
        if (!empty($userIds)) {
            $users = User::findManyCached($userIds);
        } else {
            // For listing, use traditional query with manual caching
            $users = User::where('is_active', true)
                ->limit(20)
                ->get()
                ->each(function ($user) {
                    $user->cacheRecord();
                });
        }

        return UserResource::collection($users);
    }
}
```

### Queue Job Integration

```php
<?php

namespace App\Jobs;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class WarmUserCacheJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private array $userIds;

    public function __construct(array $userIds)
    {
        $this->userIds = $userIds;
    }

    public function handle()
    {
        $cachedCount = ModelCacheService::warmCache(User::class, $this->userIds);
        
        \Log::info("Warmed cache for {$cachedCount} users", [
            'job' => self::class,
            'user_ids' => $this->userIds,
        ]);
    }
}

// Usage
class CacheWarmupService
{
    public function warmPopularUsers()
    {
        $popularUserIds = User::select('id')
            ->where('last_login_at', '>', now()->subDays(7))
            ->orderBy('login_count', 'desc')
            ->limit(1000)
            ->pluck('id')
            ->chunk(100)
            ->toArray();

        foreach ($popularUserIds as $chunk) {
            WarmUserCacheJob::dispatch($chunk);
        }
    }
}
```

## Testing Examples

### Unit Tests

```php
<?php

namespace Tests\Unit;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Tests\TestCase;

class UserCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use array driver for testing
        config(['model-cache.driver' => 'array']);
    }

    public function test_user_can_be_cached()
    {
        $user = User::factory()->create();
        
        $this->assertTrue($user->cacheRecord());
        
        $cachedUser = User::findCached($user->id);
        $this->assertNotNull($cachedUser);
        $this->assertEquals($user->id, $cachedUser->id);
    }

    public function test_user_cache_is_invalidated_on_update()
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        $user->cacheRecord();

        // Update the user
        $user->update(['name' => 'Updated Name']);

        // Cache should be refreshed
        $cachedUser = User::findCached($user->id);
        $this->assertEquals('Updated Name', $cachedUser->name);
    }

    public function test_find_many_cached()
    {
        $users = User::factory()->count(5)->create();
        $userIds = $users->pluck('id')->toArray();

        // Cache some users
        $users->take(3)->each(function ($user) {
            $user->cacheRecord();
        });

        $foundUsers = User::findManyCached($userIds);
        
        $this->assertCount(5, $foundUsers);
        $this->assertEquals($userIds, $foundUsers->pluck('id')->sort()->values()->toArray());
    }

    public function test_cache_stats()
    {
        User::factory()->count(10)->create()->each(function ($user) {
            $user->cacheRecord();
        });

        $stats = ModelCacheService::getCacheStats(User::class);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cached_records', $stats);
        $this->assertEquals(10, $stats['cached_records']);
    }
}
```

### Feature Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCacheIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_uses_cached_users()
    {
        $user = User::factory()->create();
        $user->cacheRecord();

        // Mock cache to verify it's being used
        $this->mock('cache', function ($mock) use ($user) {
            $mock->shouldReceive('get')
                ->once()
                ->with($user->getCacheKey())
                ->andReturn($user);
        });

        $response = $this->getJson("/api/users/{$user->id}");
        
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            ]);
    }

    public function test_cache_performance()
    {
        $users = User::factory()->count(100)->create();
        $userIds = $users->pluck('id')->toArray();

        // Warm cache
        $users->each(function ($user) {
            $user->cacheRecord();
        });

        // Measure cache performance
        $start = microtime(true);
        $cachedUsers = User::findManyCached($userIds);
        $cacheTime = microtime(true) - $start;

        // Clear cache and measure database performance
        User::forgetAllCache();
        
        $start = microtime(true);
        $dbUsers = User::whereIn('id', $userIds)->get();
        $dbTime = microtime(true) - $start;

        $this->assertCount(100, $cachedUsers);
        $this->assertCount(100, $dbUsers);
        $this->assertLessThan($dbTime * 0.5, $cacheTime); // Cache should be at least 2x faster
    }
}
```

### Performance Tests

```php
<?php

namespace Tests\Performance;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Tests\TestCase;

class CachePerformanceTest extends TestCase
{
    public function test_cache_hit_rate_tracking()
    {
        $users = User::factory()->count(100)->create();
        $userIds = $users->pluck('id')->toArray();

        // Warm cache for half the users
        $cachedIds = array_slice($userIds, 0, 50);
        ModelCacheService::warmCache(User::class, $cachedIds);

        // Access all users multiple times
        foreach (range(1, 10) as $iteration) {
            foreach ($userIds as $id) {
                User::findCached($id);
            }
        }

        $stats = ModelCacheService::getCacheStats(User::class);
        
        // We should have a 50% hit rate (50 cached, 50 not cached)
        $this->assertGreaterThanOrEqual(0.45, $stats['cache_hit_rate']);
        $this->assertLessThanOrEqual(0.55, $stats['cache_hit_rate']);
    }

    public function test_memory_usage_optimization()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create and cache 1000 users
        $users = User::factory()->count(1000)->create();
        $users->each(function ($user) {
            $user->cacheRecord();
        });
        
        $afterCacheMemory = memory_get_usage(true);
        $memoryUsed = $afterCacheMemory - $initialMemory;
        
        // Memory usage should be reasonable (less than 50MB for 1000 users)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
        
        echo "\nMemory used for 1000 cached users: " . 
             round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }
}
```

## Troubleshooting Examples

### Debugging Cache Issues

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class DebugCacheCommand extends Command
{
    protected $signature = 'cache:debug {model} {id?}';
    protected $description = 'Debug cache issues for a specific model';

    public function handle()
    {
        $modelClass = $this->argument('model');
        $id = $this->argument('id');

        if (!class_exists($modelClass)) {
            $this->error("Model {$modelClass} does not exist");
            return;
        }

        if ($id) {
            $this->debugSpecificRecord($modelClass, $id);
        } else {
            $this->debugModelCache($modelClass);
        }
    }

    private function debugSpecificRecord(string $modelClass, $id)
    {
        $this->info("Debugging cache for {$modelClass}#{$id}");

        // Try to find the record
        $model = $modelClass::find($id);
        if (!$model) {
            $this->error("Record not found in database");
            return;
        }

        // Check cache info
        $cacheInfo = $model->getCacheInfo();
        $this->table(['Property', 'Value'], [
            ['Cache Key', $cacheInfo['cache_key']],
            ['Is Cached', $cacheInfo['is_cached'] ? 'Yes' : 'No'],
            ['TTL Remaining', $cacheInfo['ttl_remaining'] ?? 'N/A'],
            ['Cache Size', $cacheInfo['cache_size'] ?? 'N/A'],
            ['Driver', $cacheInfo['driver']],
        ]);

        // Test caching
        $this->info("\nTesting cache operations...");
        
        $this->line("1. Clearing cache...");
        $model->forgetCache();
        
        $this->line("2. Caching record...");
        $success = $model->cacheRecord();
        $this->line($success ? "✅ Success" : "❌ Failed");
        
        $this->line("3. Retrieving from cache...");
        $cached = $modelClass::findCached($id);
        $this->line($cached ? "✅ Found" : "❌ Not found");
    }

    private function debugModelCache(string $modelClass)
    {
        $this->info("Debugging cache for {$modelClass}");

        $stats = ModelCacheService::getCacheStats($modelClass);
        
        $this->table(['Metric', 'Value'], [
            ['Total Records', $stats['total_records']],
            ['Cached Records', $stats['cached_records']],
            ['Cache Hit Rate', number_format($stats['cache_hit_rate'] * 100, 2) . '%'],
            ['Memory Usage', $stats['memory_usage']],
            ['Avg TTL', $stats['avg_ttl'] . 's'],
        ]);

        if ($stats['cache_hit_rate'] < 0.7) {
            $this->warn("⚠️  Low cache hit rate detected!");
            $this->line("Consider:");
            $this->line("- Increasing TTL");
            $this->line("- Pre-warming cache");
            $this->line("- Reviewing cache invalidation logic");
        }
    }
}
```

### Cache Health Check

```php
<?php

namespace App\Console\Commands;

use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class CacheHealthCheckCommand extends Command
{
    protected $signature = 'cache:health-check';
    protected $description = 'Perform health check on model cache';

    public function handle()
    {
        $this->info("Performing cache health check...\n");

        $models = [
            \App\Models\User::class,
            \App\Models\Product::class,
            \App\Models\Order::class,
        ];

        $issues = [];

        foreach ($models as $model) {
            $this->checkModel($model, $issues);
        }

        if (empty($issues)) {
            $this->info("✅ All cache systems are healthy!");
        } else {
            $this->error("❌ Issues found:");
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
        }
    }

    private function checkModel(string $model, array &$issues)
    {
        $this->line("Checking {$model}...");

        try {
            $stats = ModelCacheService::getCacheStats($model);

            // Check hit rate
            if ($stats['cache_hit_rate'] < 0.5) {
                $issues[] = "{$model}: Low hit rate ({$stats['cache_hit_rate']})";
            }

            // Check memory usage
            $memoryMB = $this->parseMemoryUsage($stats['memory_usage']);
            if ($memoryMB > 100) { // More than 100MB
                $issues[] = "{$model}: High memory usage ({$stats['memory_usage']})";
            }

            // Check if cache is working at all
            if ($stats['cached_records'] === 0 && $stats['total_records'] > 0) {
                $issues[] = "{$model}: No cached records found";
            }

        } catch (\Exception $e) {
            $issues[] = "{$model}: Error checking stats - {$e->getMessage()}";
        }
    }

    private function parseMemoryUsage(string $memoryUsage): float
    {
        if (str_contains($memoryUsage, 'MB')) {
            return (float) str_replace('MB', '', $memoryUsage);
        }
        
        if (str_contains($memoryUsage, 'KB')) {
            return (float) str_replace('KB', '', $memoryUsage) / 1024;
        }
        
        return 0;
    }
}
```

These examples demonstrate comprehensive usage patterns for the Laravel Model Cache package, from basic implementation to advanced optimization and troubleshooting techniques.
