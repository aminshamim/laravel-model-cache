# Laravel Model Cache

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E11.0%7C%5E12.0-red)](https://laravel.com)
[![Tests](https://img.shields.io/badge/tests-56%20passing-green)](#testing)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A high-performance Laravel package for automatic model caching with intelligent performance optimization, comprehensive monitoring, and seamless integration.

## Features

- 🚀 **Automatic Model Caching** - Zero-configuration caching with intelligent cache management
- 📊 **Performance Monitoring** - Built-in cache hit/miss tracking and optimization
- 🎯 **Dynamic TTL Adjustment** - Automatic cache expiration based on performance metrics
- 🔧 **Query Builder Integration** - Enhanced Eloquent queries with caching methods
- 🏷️ **Cache Tagging** - Efficient cache invalidation with tag support
- 📦 **Batch Operations** - Optimized multi-model caching and retrieval
- 🛡️ **Error Resilience** - Graceful fallback to database on cache failures
- 🔍 **Comprehensive Logging** - Detailed debugging and monitoring capabilities
- ⚡ **Laravel 11-12 Ready** - Full compatibility with latest Laravel versions

## Installation

Install via Composer:

```bash
composer require aminshamim/laravel-model-cache
```

### Service Provider Registration

The package will automatically register itself via Laravel's package discovery. For manual registration, add to `config/app.php`:

```php
'providers' => [
    // Other providers...
    AminShamim\LaravelModelCache\ModelCacheServiceProvider::class,
],
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="AminShamim\LaravelModelCache\ModelCacheServiceProvider"
```

## Quick Start

### 1. Add the Trait to Your Model

```php
<?php

namespace App\Models;

use AminShamim\LaravelModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use ModelCacheable;
    
    // Your model code...
}
```

### 2. Start Using Cached Methods

```php
// Find with caching
$user = User::findCached(1);

// Find multiple with caching
$users = User::findManyCached([1, 2, 3]);

// Manual caching
$user = User::find(1);
$user->cacheRecord();

// Clear cache
$user->forgetCache();
```

### 3. Query Builder Integration

```php
// Enable query builder caching in your model
protected function getCustomCacheableProperties(): array
{
    return [
        'override_find_method' => true,
    ];
}

// Now all find operations use caching automatically
$user = User::find(1); // Uses cache
$users = User::findMany([1, 2, 3]); // Uses cache

// Additional query builder methods
User::query()->findCached(1);
User::query()->findManyCached([1, 2, 3]);
User::query()->warmCache(100); // Cache first 100 records
User::query()->clearCache(); // Clear all cached records
```

## Configuration

The package provides extensive configuration options. Here's the default configuration:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    | The cache driver to use for model caching. Defaults to your app's
    | default cache driver. Redis is recommended for production.
    */
    'driver' => env('MODEL_CACHE_DRIVER', null),

    /*
    |--------------------------------------------------------------------------
    | Default TTL (Time To Live)
    |--------------------------------------------------------------------------
    | The default cache expiration time in seconds.
    */
    'ttl' => env('MODEL_CACHE_TTL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    | Prefix for all cache keys to avoid collisions.
    */
    'prefix' => env('MODEL_CACHE_PREFIX', 'model-cache'),

    /*
    |--------------------------------------------------------------------------
    | Auto Cache Invalidation
    |--------------------------------------------------------------------------
    | Automatically clear cache when models are updated or deleted.
    */
    'auto_invalidate' => env('MODEL_CACHE_AUTO_INVALIDATE', true),

    /*
    |--------------------------------------------------------------------------
    | Override Find Methods
    |--------------------------------------------------------------------------
    | Globally override Eloquent's find methods to use caching.
    */
    'override_find_method' => env('MODEL_CACHE_OVERRIDE_FIND', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Relationships
    |--------------------------------------------------------------------------
    | Whether to cache model relationships by default.
    */
    'cache_relationships' => env('MODEL_CACHE_RELATIONSHIPS', false),

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    | Enable performance tracking and dynamic TTL adjustment.
    */
    'performance_monitoring' => [
        'enabled' => env('MODEL_CACHE_PERFORMANCE_MONITORING', true),
        'hit_rate_threshold' => 0.8, // Adjust TTL when hit rate is above this
        'ttl_multiplier' => 1.5, // Multiply TTL by this factor for high-performing cache
        'max_ttl' => 3600, // Maximum TTL in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    | Configure logging for cache operations.
    */
    'logging' => [
        'enabled' => env('MODEL_CACHE_LOGGING', false),
        'channel' => env('MODEL_CACHE_LOG_CHANNEL', null),
        'level' => env('MODEL_CACHE_LOG_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    | Enable additional debugging information and logging.
    */
    'debug_mode_enabled' => env('MODEL_CACHE_DEBUG', false),
];
```
```

3. Publish the configuration file:

```bash
php artisan vendor:publish --tag=model-cache-config
```

## Configuration

The package configuration is located in `config/model-cache.php`:

```php
return [
    // How long should individual model records be cached (in seconds)?
    'ttl' => 300,

    // Cache prefix for model records
    'prefix' => 'model-cache',

    // Primary key field name (will be overridden by model's primaryKey)
    'primary_key' => 'id',

    // Whether to enable automatic cache invalidation on model events
    'auto_invalidate' => true,

    // Logging configuration
    'logging' => [
        'enabled' => false,
        'channel' => null, // null = use default channel
        'level' => 'debug',
    ],

    // Cache driver to use (null = use default cache driver)
    'driver' => null,

    // Whether to cache relationships when loading models
    'cache_relationships' => false,

    // Maximum number of cached records per model (0 = unlimited)
    'max_records_per_model' => 0,

    // Whether to override the default find() method with caching
    'override_find_method' => false,
];
```

## Usage

### Basic Usage

Add the `ModelCacheable` trait to your model:

```php
<?php

namespace App\Models;

use AminShamim\ModelCache\Models\Traits\ModelCacheable;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use ModelCacheable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    
    // ... rest of your model
}
```

### Finding Cached Records

You have two options for finding cached records:

#### Option 1: Use `findCached()` method (Recommended for gradual migration)
```php
// Find a single record with caching
$user = User::findCached(1);

// Find multiple records with caching
$users = User::findManyCached([1, 2, 3, 4, 5]);
```

#### Option 2: Override the default `find()` method (Automatic caching)
```php
// Configure in your model or globally
public function getCacheableProperties(): array
{
    return [
        'override_find_method' => true, // This will make find() use caching
        // ... other properties
    ];
}

// Now regular find() calls will use caching automatically
$user = User::find(1); // This will use caching if override_find_method is true
$users = User::findMany([1, 2, 3]); // This will use caching if configured
```

### Manual Cache Management

```php
$user = User::find(1);

// Cache the record manually
$user->cacheRecord();

// Forget the cache for this record
$user->forgetCache();

// Forget all cache for this model class
User::forgetAllCache();
```

### Custom Configuration per Model

Override the `getCacheableProperties` method in your model:

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
            'ttl' => 600, // 10 minutes
            'prefix' => 'users-cache',
            'primary_key' => $this->getKeyName(),
            'auto_invalidate' => true,
            'logging' => [
                'enabled' => true,
                'channel' => 'cache',
                'level' => 'info',
            ],
            'driver' => 'redis',
            'cache_relationships' => false,
            'max_records_per_model' => 1000,
            'override_find_method' => true, // Override find() with caching
        ];
    }
}
```

### Using the Cache Service

```php
use AminShamim\ModelCache\Services\ModelCacheService;

// Warm up cache for all users
$cachedCount = ModelCacheService::warmCache(User::class);

// Warm up cache for specific users
$cachedCount = ModelCacheService::warmCache(User::class, [1, 2, 3, 4, 5]);

// Get cache statistics
$stats = ModelCacheService::getCacheStats(User::class);

// Clear cache for specific records
ModelCacheService::clearCacheForRecords(User::class, [1, 2, 3]);

// Optimize cache (remove old records if over limit)
$result = ModelCacheService::optimizeCache(User::class);
```

## Cache Keys

The package generates cache keys in the following format:

```
{prefix}:{model_class}:{primary_key}
```

Example:
```
model-cache:App\Models\User:123
```

## Logging

Enable logging in your configuration:

```php
'logging' => [
    'enabled' => true,
    'channel' => 'cache',
    'level' => 'debug',
],
```

Log messages include:
- Model cached
- Model found in cache
- Model cache forgotten
- Cache errors

## Performance Benefits

1. **Individual Record Access**: Only cache the specific records you need
2. **Reduced Memory Usage**: No need to cache entire query results
3. **Faster Cache Invalidation**: Clear only specific records when they change
4. **Better Cache Hit Rates**: More granular caching leads to better hit rates
5. **Easy Migration**: Option to override `find()` method reduces refactoring effort

## Comparison with Query-Level Caching

| Feature | Query-Level Caching | Model-Level Caching |
|---------|-------------------|-------------------|
| Cache Granularity | Entire query results | Individual records |
| Memory Usage | High (caches all results) | Low (caches only needed records) |
| Cache Invalidation | Clear entire query cache | Clear specific record cache |
| Cache Hit Rate | Lower (specific queries) | Higher (individual records) |
| Performance | Good for repeated queries | Better for individual record access |

## Advanced Usage

### Custom Cache Keys

Override the `getCacheKey` method in your model:

```php
protected function getCacheKey(): string
{
    $properties = $this->getCacheableProperties();
    $prefix = $properties['prefix'];
    $modelClass = static::class;
    $primaryKey = $this->getKey();
    
    // Add additional context to cache key
    $context = $this->getCacheContext();
    
    return "{$prefix}:{$modelClass}:{$primaryKey}:{$context}";
}

protected function getCacheContext(): string
{
    // Add any additional context (e.g., user role, language, etc.)
    return auth()->user()?->role ?? 'guest';
}
```

### Conditional Caching

Override the `shouldCache` method:

```php
protected function shouldCache(): bool
{
    // Don't cache if the record is soft deleted
    if ($this->trashed()) {
        return false;
    }
    
    // Don't cache if the user is not active
    if (!$this->is_active) {
        return false;
    }
    
    return parent::shouldCache();
}
```

## Testing

```php
use AminShamim\ModelCache\Services\ModelCacheService;

class UserTest extends TestCase
{
    public function test_user_caching()
    {
        $user = User::factory()->create();
        
        // Cache should be created automatically
        $this->assertTrue($user->cacheRecord());
        
        // Find from cache
        $cachedUser = User::findCached($user->id);
        $this->assertEquals($user->id, $cachedUser->id);
        
        // Update should refresh cache
        $user->update(['name' => 'Updated Name']);
        $updatedUser = User::findCached($user->id);
        $this->assertEquals('Updated Name', $updatedUser->name);
    }
}
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). 
