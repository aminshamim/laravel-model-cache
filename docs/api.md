# API Reference

## Overview

This document provides a complete API reference for the Laravel Model Cache package, including all available methods, classes, and interfaces.

## Table of Contents

- [Trait Methods](#trait-methods)
- [Service Methods](#service-methods)
- [Facade Methods](#facade-methods)
- [Contracts/Interfaces](#contractsinterfaces)
- [Query Builder](#query-builder)
- [Events](#events)
- [Exceptions](#exceptions)

## Trait Methods

### ModelCacheable Trait

The main trait that provides caching functionality to Eloquent models.

#### findCached()

Find a model by its primary key with caching.

```php
public static function findCached($id, array $columns = ['*']): ?Model
```

**Parameters:**
- `$id` (mixed): The primary key value
- `$columns` (array): Columns to select (default: `['*']`)

**Returns:** Model instance or `null`

**Example:**
```php
$user = User::findCached(1);
$user = User::findCached(1, ['id', 'name', 'email']);
```

#### findManyCached()

Find multiple models by their primary keys with caching.

```php
public static function findManyCached(array $ids, array $columns = ['*']): Collection
```

**Parameters:**
- `$ids` (array): Array of primary key values
- `$columns` (array): Columns to select (default: `['*']`)

**Returns:** Collection of models

**Example:**
```php
$users = User::findManyCached([1, 2, 3, 4, 5]);
$users = User::findManyCached([1, 2, 3], ['id', 'name']);
```

#### cacheRecord()

Cache the current model instance.

```php
public function cacheRecord(): bool
```

**Returns:** `true` if successful, `false` otherwise

**Example:**
```php
$user = User::find(1);
$success = $user->cacheRecord();
```

#### forgetCache()

Remove the current model from cache.

```php
public function forgetCache(): bool
```

**Returns:** `true` if successful, `false` otherwise

**Example:**
```php
$user = User::find(1);
$user->forgetCache();
```

#### forgetAllCache()

Remove all cached records for the model class.

```php
public static function forgetAllCache(): bool
```

**Returns:** `true` if successful, `false` otherwise

**Example:**
```php
User::forgetAllCache();
```

#### getCacheableProperties()

Get caching configuration for the model.

```php
public function getCacheableProperties(): array
```

**Returns:** Array of configuration options

**Example:**
```php
public function getCacheableProperties(): array
{
    return [
        'ttl' => 600,
        'prefix' => 'users',
        'auto_invalidate' => true,
        'logging' => ['enabled' => true],
        'driver' => 'redis',
    ];
}
```

#### shouldCache()

Determine if the model should be cached.

```php
protected function shouldCache(): bool
```

**Returns:** `true` if the model should be cached, `false` otherwise

**Example:**
```php
protected function shouldCache(): bool
{
    // Don't cache soft-deleted records
    if ($this->trashed()) {
        return false;
    }
    
    return parent::shouldCache();
}
```

#### getCacheKey()

Get the cache key for the model.

```php
protected function getCacheKey(): string
```

**Returns:** Cache key string

**Example:**
```php
protected function getCacheKey(): string
{
    $baseKey = parent::getCacheKey();
    $context = auth()->user()?->role ?? 'guest';
    
    return "{$baseKey}:{$context}";
}
```

#### getCacheTags()

Get cache tags for the model (if supported by driver).

```php
protected function getCacheTags(): array
```

**Returns:** Array of cache tags

**Example:**
```php
protected function getCacheTags(): array
{
    return [
        'users',
        'user:' . $this->id,
        'role:' . $this->role,
    ];
}
```

## Service Methods

### ModelCacheService

Main service class for cache operations.

#### warmCache()

Pre-load models into cache.

```php
public static function warmCache(string $modelClass, array $ids = null): int
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name
- `$ids` (array|null): Specific IDs to warm (null = all records)

**Returns:** Number of records cached

**Example:**
```php
// Warm all users
$count = ModelCacheService::warmCache(User::class);

// Warm specific users
$count = ModelCacheService::warmCache(User::class, [1, 2, 3, 4, 5]);
```

#### getCacheStats()

Get cache statistics for a model.

```php
public static function getCacheStats(string $modelClass): array
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name

**Returns:** Array of statistics

**Example:**
```php
$stats = ModelCacheService::getCacheStats(User::class);
/*
[
    'total_records' => 1000,
    'cached_records' => 750,
    'cache_hit_rate' => 0.85,
    'avg_ttl' => 300,
    'memory_usage' => '2.5MB'
]
*/
```

#### clearCacheForRecords()

Clear cache for specific model records.

```php
public static function clearCacheForRecords(string $modelClass, array $ids): int
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name
- `$ids` (array): Array of primary key values

**Returns:** Number of cache entries cleared

**Example:**
```php
$cleared = ModelCacheService::clearCacheForRecords(User::class, [1, 2, 3]);
```

#### optimizeCache()

Optimize cache by removing old/excess records.

```php
public static function optimizeCache(string $modelClass): array
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name

**Returns:** Array with optimization results

**Example:**
```php
$result = ModelCacheService::optimizeCache(User::class);
/*
[
    'records_removed' => 50,
    'memory_freed' => '1.2MB',
    'optimization_time' => 0.15
]
*/
```

#### flushModelCache()

Flush all cache for a specific model.

```php
public static function flushModelCache(string $modelClass): bool
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name

**Returns:** `true` if successful, `false` otherwise

**Example:**
```php
$success = ModelCacheService::flushModelCache(User::class);
```

#### getCacheSize()

Get cache size information for a model.

```php
public static function getCacheSize(string $modelClass): array
```

**Parameters:**
- `$modelClass` (string): Fully qualified model class name

**Returns:** Array with size information

**Example:**
```php
$size = ModelCacheService::getCacheSize(User::class);
/*
[
    'total_keys' => 1000,
    'total_size_bytes' => 2621440,
    'total_size_human' => '2.5MB',
    'avg_record_size' => 2621
]
*/
```

## Facade Methods

### ModelCache Facade

Convenient facade for accessing cache service methods.

```php
use AminShamim\ModelCache\Facades\ModelCache;

// All service methods are available through the facade
$stats = ModelCache::getCacheStats(User::class);
$count = ModelCache::warmCache(User::class);
$cleared = ModelCache::clearCacheForRecords(User::class, [1, 2, 3]);
```

## Contracts/Interfaces

### ModelCacheServiceContract

Interface defining the cache service contract.

```php
interface ModelCacheServiceContract
{
    public function warmCache(string $modelClass, array $ids = null): int;
    public function getCacheStats(string $modelClass): array;
    public function clearCacheForRecords(string $modelClass, array $ids): int;
    public function optimizeCache(string $modelClass): array;
    public function flushModelCache(string $modelClass): bool;
    public function getCacheSize(string $modelClass): array;
}
```

### CacheableModelContract

Interface for cacheable models.

```php
interface CacheableModelContract
{
    public function getCacheableProperties(): array;
    public function cacheRecord(): bool;
    public function forgetCache(): bool;
    public static function forgetAllCache(): bool;
    public static function findCached($id, array $columns = ['*']);
    public static function findManyCached(array $ids, array $columns = ['*']);
}
```

## Query Builder

### CacheableQueryBuilder

Extended query builder with caching capabilities.

#### find()

Override of the standard find method with optional caching.

```php
public function find($id, $columns = ['*'])
```

**Note:** Only uses caching if `override_find_method` is enabled in model configuration.

#### findMany()

Override of the standard findMany method with optional caching.

```php
public function findMany($ids, $columns = ['*'])
```

#### withCache()

Force the next query to use caching.

```php
public function withCache(int $ttl = null): self
```

**Parameters:**
- `$ttl` (int|null): Custom TTL for this query (optional)

**Returns:** Query builder instance

**Example:**
```php
$users = User::query()->withCache(600)->where('active', true)->get();
```

#### withoutCache()

Force the next query to bypass caching.

```php
public function withoutCache(): self
```

**Returns:** Query builder instance

**Example:**
```php
$users = User::query()->withoutCache()->where('active', true)->get();
```

## Events

### Model Cache Events

The package fires several events during cache operations:

#### ModelCached

Fired when a model is successfully cached.

```php
use AminShamim\ModelCache\Events\ModelCached;

class ModelCachedListener
{
    public function handle(ModelCached $event)
    {
        $model = $event->model;
        $cacheKey = $event->cacheKey;
        $ttl = $event->ttl;
        
        // Handle the event
    }
}
```

#### ModelCacheHit

Fired when a cached model is retrieved.

```php
use AminShamim\ModelCache\Events\ModelCacheHit;

class ModelCacheHitListener
{
    public function handle(ModelCacheHit $event)
    {
        $model = $event->model;
        $cacheKey = $event->cacheKey;
        
        // Track cache hits for analytics
    }
}
```

#### ModelCacheMiss

Fired when a cache miss occurs.

```php
use AminShamim\ModelCache\Events\ModelCacheMiss;

class ModelCacheMissListener
{
    public function handle(ModelCacheMiss $event)
    {
        $modelClass = $event->modelClass;
        $id = $event->id;
        $cacheKey = $event->cacheKey;
        
        // Track cache misses
    }
}
```

#### ModelCacheCleared

Fired when cache is cleared for a model.

```php
use AminShamim\ModelCache\Events\ModelCacheCleared;

class ModelCacheClearedListener
{
    public function handle(ModelCacheCleared $event)
    {
        $model = $event->model;
        $cacheKey = $event->cacheKey;
        
        // Handle cache clearing
    }
}
```

### Registering Event Listeners

```php
// In your EventServiceProvider
protected $listen = [
    ModelCached::class => [
        ModelCachedListener::class,
    ],
    ModelCacheHit::class => [
        ModelCacheHitListener::class,
    ],
    ModelCacheMiss::class => [
        ModelCacheMissListener::class,
    ],
    ModelCacheCleared::class => [
        ModelCacheClearedListener::class,
    ],
];
```

## Exceptions

### ModelCacheException

Base exception for all cache-related errors.

```php
use AminShamim\ModelCache\Exceptions\ModelCacheException;

try {
    $user = User::findCached(1);
} catch (ModelCacheException $e) {
    // Handle cache error
    Log::error('Cache error: ' . $e->getMessage());
}
```

### CacheDriverException

Thrown when there are cache driver-related issues.

```php
use AminShamim\ModelCache\Exceptions\CacheDriverException;

try {
    ModelCacheService::warmCache(User::class);
} catch (CacheDriverException $e) {
    // Handle driver error
    Log::error('Cache driver error: ' . $e->getMessage());
}
```

### InvalidModelException

Thrown when trying to cache an invalid model.

```php
use AminShamim\ModelCache\Exceptions\InvalidModelException;

try {
    $invalidModel = new stdClass();
    $invalidModel->cacheRecord();
} catch (InvalidModelException $e) {
    // Handle invalid model error
}
```

## Method Chaining Examples

### Complex Query with Caching

```php
$users = User::query()
    ->withCache(600) // Cache for 10 minutes
    ->where('active', true)
    ->where('created_at', '>', now()->subMonth())
    ->orderBy('last_login_at', 'desc')
    ->limit(50)
    ->get();
```

### Conditional Caching

```php
$query = User::query()->where('active', true);

if ($useCache) {
    $query->withCache();
} else {
    $query->withoutCache();
}

$users = $query->get();
```

## Performance Methods

### Performance Monitoring

#### getPerformanceMetrics()

Get detailed performance metrics for cached models.

```php
public static function getPerformanceMetrics(string $modelClass): array
```

**Returns:**
```php
[
    'hit_rate' => 0.85,
    'miss_rate' => 0.15,
    'avg_response_time' => 0.025,
    'cache_size' => '2.5MB',
    'total_requests' => 10000,
    'cache_hits' => 8500,
    'cache_misses' => 1500
]
```

#### adjustTTL()

Dynamically adjust TTL based on performance metrics.

```php
public static function adjustTTL(string $modelClass): int
```

**Returns:** New TTL value in seconds

## Cache Tags (Redis Only)

### Tag-Based Cache Invalidation

```php
// Models can return cache tags
protected function getCacheTags(): array
{
    return [
        'users',
        'user:' . $this->id,
        'role:' . $this->role,
    ];
}

// Invalidate by tags
Cache::tags(['users'])->flush();
Cache::tags(['role:admin'])->flush();
```

## Debugging Methods

### getCacheInfo()

Get detailed cache information for debugging.

```php
public function getCacheInfo(): array
```

**Returns:**
```php
[
    'cache_key' => 'model-cache:App\Models\User:1',
    'is_cached' => true,
    'ttl_remaining' => 250,
    'cache_size' => 1024,
    'tags' => ['users', 'user:1'],
    'driver' => 'redis',
    'created_at' => '2024-01-15 10:30:00',
    'last_accessed' => '2024-01-15 10:35:00'
]
```

### validateCacheIntegrity()

Validate cache integrity for a model.

```php
public static function validateCacheIntegrity(string $modelClass): array
```

**Returns:**
```php
[
    'total_models' => 1000,
    'cached_models' => 850,
    'invalid_cache_entries' => 5,
    'missing_cache_entries' => 150,
    'integrity_score' => 0.99
]
```

## Advanced Usage Patterns

### Custom Cache Driver

```php
// Register custom cache driver
$cacheManager = app('cache');
$cacheManager->extend('custom', function ($app, $config) {
    return new CustomCacheStore($config);
});

// Use in model
public function getCacheableProperties(): array
{
    return [
        'driver' => 'custom',
        'ttl' => 300,
    ];
}
```

### Conditional Method Override

```php
public function getCacheableProperties(): array
{
    $config = parent::getCacheableProperties();
    
    // Override find method only for specific conditions
    if ($this->shouldOverrideFindMethod()) {
        $config['override_find_method'] = true;
    }
    
    return $config;
}

private function shouldOverrideFindMethod(): bool
{
    // Custom logic to determine when to override
    return config('app.env') === 'production' && 
           $this->getTable() === 'high_traffic_table';
}
```

This API reference provides comprehensive documentation for all available methods and features in the Laravel Model Cache package. Use this as your primary reference when implementing caching in your Laravel applications.
