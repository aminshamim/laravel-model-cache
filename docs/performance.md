# Performance Guide

## Overview

This guide covers performance optimization techniques, monitoring, and best practices for the Laravel Model Cache package. Learn how to maximize cache effectiveness and minimize resource usage.

## Table of Contents

- [Performance Monitoring](#performance-monitoring)
- [Cache Hit Rate Optimization](#cache-hit-rate-optimization)
- [Memory Management](#memory-management)
- [TTL Optimization](#ttl-optimization)
- [Driver Performance](#driver-performance)
- [Query Optimization](#query-optimization)
- [Benchmarking](#benchmarking)
- [Production Optimization](#production-optimization)

## Performance Monitoring

### Enabling Performance Monitoring

Enable performance monitoring in your configuration:

```php
// config/model-cache.php
'performance_monitoring' => [
    'enabled' => true,
    'hit_rate_threshold' => 0.8,
    'ttl_multiplier' => 1.5,
    'max_ttl' => 3600,
],
```

### Performance Metrics

Get detailed performance metrics for your cached models:

```php
use AminShamim\ModelCache\Services\ModelCacheService;

// Get performance stats for a specific model
$stats = ModelCacheService::getCacheStats(User::class);

/*
Returns:
[
    'total_records' => 1000,
    'cached_records' => 850,
    'cache_hit_rate' => 0.85,
    'cache_miss_rate' => 0.15,
    'avg_ttl' => 300,
    'memory_usage' => '2.5MB',
    'avg_response_time' => 0.025,
    'total_requests' => 10000,
    'cache_hits' => 8500,
    'cache_misses' => 1500
]
*/
```

### Real-Time Monitoring

Create a monitoring command to track cache performance:

```php
<?php

namespace App\Console\Commands;

use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class MonitorCachePerformance extends Command
{
    protected $signature = 'cache:monitor {model?}';
    protected $description = 'Monitor cache performance metrics';

    public function handle()
    {
        $model = $this->argument('model');
        
        if ($model) {
            $this->monitorModel($model);
        } else {
            $this->monitorAllModels();
        }
    }

    private function monitorModel(string $modelClass)
    {
        $stats = ModelCacheService::getCacheStats($modelClass);
        
        $this->table(['Metric', 'Value'], [
            ['Hit Rate', number_format($stats['cache_hit_rate'] * 100, 2) . '%'],
            ['Miss Rate', number_format($stats['cache_miss_rate'] * 100, 2) . '%'],
            ['Total Requests', number_format($stats['total_requests'])],
            ['Cache Hits', number_format($stats['cache_hits'])],
            ['Cache Misses', number_format($stats['cache_misses'])],
            ['Memory Usage', $stats['memory_usage']],
            ['Avg Response Time', $stats['avg_response_time'] . 'ms'],
        ]);
        
        // Alert if performance is poor
        if ($stats['cache_hit_rate'] < 0.7) {
            $this->warn('⚠️  Low cache hit rate detected!');
            $this->line('Consider optimizing your caching strategy.');
        }
    }
}
```

## Cache Hit Rate Optimization

### Understanding Hit Rates

- **Excellent**: 90%+ hit rate
- **Good**: 80-90% hit rate
- **Average**: 70-80% hit rate
- **Poor**: <70% hit rate

### Strategies to Improve Hit Rates

#### 1. Optimize TTL Values

```php
public function getCacheableProperties(): array
{
    return [
        // Longer TTL for stable data
        'ttl' => $this->isStableData() ? 3600 : 300,
    ];
}

private function isStableData(): bool
{
    // Data that rarely changes
    return in_array($this->getTable(), [
        'countries',
        'currencies',
        'categories',
        'settings'
    ]);
}
```

#### 2. Preload Frequently Accessed Data

```php
// Warm cache for popular records
$popularUserIds = [1, 2, 3, 4, 5]; // Most active users
ModelCacheService::warmCache(User::class, $popularUserIds);

// Warm cache based on access patterns
$recentlyAccessedIds = Cache::get('recently_accessed_users', []);
ModelCacheService::warmCache(User::class, $recentlyAccessedIds);
```

#### 3. Dynamic TTL Based on Access Patterns

```php
public function getCacheableProperties(): array
{
    $baseConfig = [
        'ttl' => 300,
        'auto_invalidate' => true,
    ];

    // Increase TTL for frequently accessed records
    $accessCount = $this->getAccessCount();
    if ($accessCount > 100) {
        $baseConfig['ttl'] = 1800; // 30 minutes
    } elseif ($accessCount > 50) {
        $baseConfig['ttl'] = 900;  // 15 minutes
    }

    return $baseConfig;
}

private function getAccessCount(): int
{
    return Cache::get("access_count:{$this->getTable()}:{$this->getKey()}", 0);
}
```

## Memory Management

### Monitor Memory Usage

```php
// Get memory usage for specific models
$userCacheSize = ModelCacheService::getCacheSize(User::class);
$productCacheSize = ModelCacheService::getCacheSize(Product::class);

echo "User cache: {$userCacheSize['total_size_human']}\n";
echo "Product cache: {$productCacheSize['total_size_human']}\n";
```

### Implement Cache Limits

```php
// config/model-cache.php
'max_records_per_model' => [
    User::class => 1000,      // Limit user cache to 1000 records
    Product::class => 5000,   // Products can have more cache
    Category::class => 100,   // Categories are few but stable
],
```

### Automatic Cache Cleanup

```php
<?php

namespace App\Console\Commands;

use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class OptimizeCacheCommand extends Command
{
    protected $signature = 'cache:optimize {--force}';
    protected $description = 'Optimize model cache by removing old records';

    public function handle()
    {
        $models = [
            \App\Models\User::class,
            \App\Models\Product::class,
            \App\Models\Order::class,
        ];

        foreach ($models as $model) {
            $this->info("Optimizing cache for {$model}...");
            
            $result = ModelCacheService::optimizeCache($model);
            
            $this->line("  Records removed: {$result['records_removed']}");
            $this->line("  Memory freed: {$result['memory_freed']}");
            $this->line("  Time taken: {$result['optimization_time']}s");
        }
    }
}
```

### Memory-Efficient Caching

```php
public function getCacheableProperties(): array
{
    return [
        'ttl' => 300,
        // Only cache essential columns
        'cacheable_columns' => ['id', 'name', 'email', 'status'],
        // Exclude large text fields
        'excluded_columns' => ['bio', 'description', 'notes'],
    ];
}

// Override toArray to reduce cached data size
public function toArray()
{
    $array = parent::toArray();
    
    // Remove large fields if caching
    if ($this->isCaching()) {
        unset($array['large_field'], $array['blob_data']);
    }
    
    return $array;
}
```

## TTL Optimization

### Dynamic TTL Adjustment

```php
use AminShamim\ModelCache\Services\ModelCacheService;

// Automatically adjust TTL based on performance
$newTTL = ModelCacheService::adjustTTL(User::class);

// Manual TTL optimization
public function getOptimalTTL(): int
{
    $stats = ModelCacheService::getCacheStats(static::class);
    
    // High hit rate = increase TTL
    if ($stats['cache_hit_rate'] > 0.9) {
        return min($this->getCacheableProperties()['ttl'] * 1.5, 3600);
    }
    
    // Low hit rate = decrease TTL
    if ($stats['cache_hit_rate'] < 0.7) {
        return max($this->getCacheableProperties()['ttl'] * 0.7, 60);
    }
    
    return $this->getCacheableProperties()['ttl'];
}
```

### Context-Based TTL

```php
public function getCacheableProperties(): array
{
    $ttl = 300; // Default 5 minutes
    
    // Adjust based on model state
    if ($this->isSystemCritical()) {
        $ttl = 60; // 1 minute for critical data
    } elseif ($this->isHistoricalData()) {
        $ttl = 3600; // 1 hour for historical data
    } elseif ($this->isUserSpecific()) {
        $ttl = 900; // 15 minutes for user data
    }
    
    return ['ttl' => $ttl];
}
```

### Time-Based TTL

```php
public function getCacheableProperties(): array
{
    $hour = now()->hour;
    
    // Longer TTL during off-peak hours
    if ($hour >= 22 || $hour <= 6) {
        $ttl = 1800; // 30 minutes
    } else {
        $ttl = 300;  // 5 minutes
    }
    
    return ['ttl' => $ttl];
}
```

## Driver Performance

### Cache Driver Comparison

| Driver | Read Speed | Write Speed | Memory Usage | Features |
|--------|------------|-------------|--------------|----------|
| Redis | Very Fast | Fast | Low | Tags, Persistence, Clustering |
| Memcached | Very Fast | Very Fast | Very Low | Simple, Fast |
| File | Slow | Slow | None | Simple, No Dependencies |
| Array | Very Fast | Very Fast | High | Testing Only |

### Redis Optimization

```php
// config/database.php - Redis optimization
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', ''),
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        
        // Performance optimizations
        'read_write_timeout' => 60,
        'timeout' => 1,
        'retry_interval' => 100,
        'persistent' => true,
        
        // Redis-specific optimizations
        'options' => [
            'compression' => Redis::COMPRESSION_LZ4,
            'serializer' => Redis::SERIALIZER_IGBINARY,
        ],
    ],
],
```

### Connection Pooling

```php
// For high-traffic applications
'redis' => [
    'clusters' => [
        'cache' => [
            [
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 1,
            ],
            [
                'host' => '127.0.0.1',
                'port' => 6380,
                'database' => 1,
            ],
        ],
    ],
],
```

## Query Optimization

### Efficient Batch Operations

```php
// Instead of multiple individual cache operations
foreach ($userIds as $id) {
    User::findCached($id); // Multiple cache calls
}

// Use batch operations
$users = User::findManyCached($userIds); // Single cache operation
```

### Selective Caching

```php
// Cache only what you need
$users = User::select(['id', 'name', 'email'])
    ->whereIn('id', $userIds)
    ->get()
    ->each(function ($user) {
        $user->cacheRecord();
    });
```

### Query Result Caching

```php
// For complex queries, cache the results
$expensiveQuery = function () {
    return User::with(['orders', 'profile'])
        ->whereHas('orders', function ($query) {
            $query->where('total', '>', 1000);
        })
        ->get();
};

$results = Cache::remember('expensive_user_query', 600, $expensiveQuery);
```

## Benchmarking

### Performance Testing

```php
<?php

namespace Tests\Performance;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Tests\TestCase;

class CachePerformanceTest extends TestCase
{
    public function test_cache_vs_database_performance()
    {
        // Create test data
        $users = User::factory()->count(1000)->create();
        $userIds = $users->pluck('id')->toArray();
        
        // Warm cache
        ModelCacheService::warmCache(User::class, $userIds);
        
        // Benchmark database access
        $dbStart = microtime(true);
        foreach (array_slice($userIds, 0, 100) as $id) {
            User::find($id);
        }
        $dbTime = microtime(true) - $dbStart;
        
        // Benchmark cache access
        $cacheStart = microtime(true);
        foreach (array_slice($userIds, 0, 100) as $id) {
            User::findCached($id);
        }
        $cacheTime = microtime(true) - $cacheStart;
        
        $this->assertLessThan($dbTime * 0.5, $cacheTime);
        
        echo "\nDatabase time: " . round($dbTime * 1000, 2) . "ms\n";
        echo "Cache time: " . round($cacheTime * 1000, 2) . "ms\n";
        echo "Speed improvement: " . round($dbTime / $cacheTime, 2) . "x\n";
    }
    
    public function test_memory_usage()
    {
        $users = User::factory()->count(100)->create();
        
        // Measure memory before caching
        $memoryBefore = memory_get_usage(true);
        
        // Cache all users
        foreach ($users as $user) {
            $user->cacheRecord();
        }
        
        // Measure memory after caching
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        echo "\nMemory used for 100 cached users: " . 
             round($memoryUsed / 1024 / 1024, 2) . "MB\n";
        
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed); // Less than 10MB
    }
}
```

### Load Testing

```php
<?php

namespace Tests\Performance;

use App\Models\User;
use Tests\TestCase;

class CacheLoadTest extends TestCase
{
    public function test_concurrent_cache_access()
    {
        $user = User::factory()->create();
        $user->cacheRecord();
        
        $processes = [];
        $results = [];
        
        // Simulate concurrent access
        for ($i = 0; $i < 10; $i++) {
            $processes[] = function () use ($user) {
                $start = microtime(true);
                $cachedUser = User::findCached($user->id);
                $end = microtime(true);
                
                return [
                    'success' => $cachedUser !== null,
                    'time' => $end - $start,
                ];
            };
        }
        
        // Execute all processes
        foreach ($processes as $process) {
            $results[] = $process();
        }
        
        // Verify all succeeded
        foreach ($results as $result) {
            $this->assertTrue($result['success']);
            $this->assertLessThan(0.1, $result['time']); // Less than 100ms
        }
        
        $avgTime = array_sum(array_column($results, 'time')) / count($results);
        echo "\nAverage concurrent access time: " . round($avgTime * 1000, 2) . "ms\n";
    }
}
```

## Production Optimization

### Production Configuration

```php
// config/model-cache.php - Production settings
return [
    'ttl' => 1800, // 30 minutes
    'driver' => 'redis',
    'auto_invalidate' => true,
    
    'performance_monitoring' => [
        'enabled' => true,
        'hit_rate_threshold' => 0.85,
        'ttl_multiplier' => 2.0,
        'max_ttl' => 7200, // 2 hours
    ],
    
    'logging' => [
        'enabled' => false, // Disable in production
    ],
    
    'debug_mode_enabled' => false,
];
```

### Monitoring Dashboard

Create a cache monitoring dashboard:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use AminShamim\ModelCache\Services\ModelCacheService;

class CacheMonitoringController extends Controller
{
    public function dashboard()
    {
        $models = [
            \App\Models\User::class,
            \App\Models\Product::class,
            \App\Models\Order::class,
        ];
        
        $stats = [];
        foreach ($models as $model) {
            $stats[$model] = ModelCacheService::getCacheStats($model);
        }
        
        return view('admin.cache-dashboard', compact('stats'));
    }
    
    public function optimize()
    {
        $models = [
            \App\Models\User::class,
            \App\Models\Product::class,
            \App\Models\Order::class,
        ];
        
        $results = [];
        foreach ($models as $model) {
            $results[$model] = ModelCacheService::optimizeCache($model);
        }
        
        return response()->json($results);
    }
}
```

### Automated Optimization

```php
<?php

namespace App\Console\Commands;

use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class AutoOptimizeCacheCommand extends Command
{
    protected $signature = 'cache:auto-optimize';
    protected $description = 'Automatically optimize cache based on performance metrics';

    public function handle()
    {
        $models = config('cache.monitored_models', []);
        
        foreach ($models as $model) {
            $stats = ModelCacheService::getCacheStats($model);
            
            // Auto-optimize if hit rate is low
            if ($stats['cache_hit_rate'] < 0.7) {
                $this->warn("Low hit rate for {$model}: {$stats['cache_hit_rate']}");
                
                // Reduce TTL for low-performing cache
                $this->adjustModelTTL($model, 0.8);
                
                // Optimize cache
                ModelCacheService::optimizeCache($model);
            }
            
            // Extend TTL for high-performing cache
            if ($stats['cache_hit_rate'] > 0.9) {
                $this->adjustModelTTL($model, 1.2);
            }
        }
    }
    
    private function adjustModelTTL(string $model, float $multiplier)
    {
        // Implementation depends on your TTL management strategy
        $this->info("Adjusting TTL for {$model} by factor {$multiplier}");
    }
}
```

### Cache Warming Strategy

```php
<?php

namespace App\Console\Commands;

use AminShamim\ModelCache\Services\ModelCacheService;
use Illuminate\Console\Command;

class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm {--model=} {--limit=1000}';
    protected $description = 'Warm cache with frequently accessed records';

    public function handle()
    {
        $model = $this->option('model');
        $limit = $this->option('limit');
        
        if ($model) {
            $this->warmModel($model, $limit);
        } else {
            $this->warmAllModels($limit);
        }
    }
    
    private function warmModel(string $modelClass, int $limit)
    {
        $this->info("Warming cache for {$modelClass}...");
        
        // Get frequently accessed records
        $frequentIds = $this->getFrequentlyAccessedIds($modelClass, $limit);
        
        if (empty($frequentIds)) {
            $this->warn("No frequently accessed records found for {$modelClass}");
            return;
        }
        
        $cachedCount = ModelCacheService::warmCache($modelClass, $frequentIds);
        
        $this->info("Cached {$cachedCount} records for {$modelClass}");
    }
    
    private function getFrequentlyAccessedIds(string $modelClass, int $limit): array
    {
        // Implementation based on your access tracking
        // This could come from analytics, logs, or a separate tracking table
        return [];
    }
}
```

## Performance Best Practices

### 1. Choose the Right TTL

- **Static data**: 1-24 hours
- **User data**: 5-30 minutes
- **Real-time data**: 30 seconds - 5 minutes

### 2. Monitor Cache Hit Rates

- Aim for 80%+ hit rates
- Investigate and optimize models with <70% hit rates

### 3. Use Appropriate Cache Drivers

- **Redis**: Production, clustering, persistence
- **Memcached**: High-performance, simple caching
- **Array**: Testing only

### 4. Implement Cache Warming

- Warm frequently accessed data
- Use background jobs for warming
- Monitor and adjust warming strategies

### 5. Optimize Memory Usage

- Cache only necessary columns
- Set reasonable cache limits
- Implement automatic cleanup

### 6. Monitor and Alert

- Set up monitoring dashboards
- Create alerts for poor performance
- Regularly review cache metrics

By following these performance optimization techniques, you can achieve significant improvements in application response times and reduce database load.
