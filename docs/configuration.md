# Configuration Guide

## Overview

The Laravel Model Cache package provides extensive configuration options to customize caching behavior according to your application's needs. This guide covers all available configuration options and best practices.

## Configuration File

The main configuration file is located at `config/model-cache.php`. You can publish it using:

```bash
php artisan vendor:publish --tag=model-cache-config
```

## Configuration Options

### Basic Settings

#### TTL (Time To Live)

```php
'ttl' => env('MODEL_CACHE_TTL', 300),
```

Controls how long cached records remain valid (in seconds).

**Recommendations:**
- **Development**: 60-300 seconds (1-5 minutes)
- **Testing**: 30 seconds
- **Production**: 300-3600 seconds (5-60 minutes)

#### Cache Prefix

```php
'prefix' => env('MODEL_CACHE_PREFIX', 'model-cache'),
```

Prefix for all cache keys to avoid conflicts with other cache data.

**Examples:**
- `model-cache` (default)
- `app-models`
- `{app_name}-models`

#### Primary Key Field

```php
'primary_key' => env('MODEL_CACHE_PRIMARY_KEY', 'id'),
```

Default primary key field name. This is overridden by each model's `getKeyName()` method.

### Cache Driver Configuration

#### Driver Selection

```php
'driver' => env('MODEL_CACHE_DRIVER', null),
```

Specify which cache driver to use. `null` uses the default cache driver.

**Options:**
- `redis` (recommended for production)
- `memcached`
- `file`
- `array` (for testing)
- `null` (use default)

#### Cache Store Selection

```php
'store' => env('MODEL_CACHE_STORE', null),
```

Specify which cache store to use if you have multiple stores configured.

### Auto-Invalidation

```php
'auto_invalidate' => env('MODEL_CACHE_AUTO_INVALIDATE', true),
```

Automatically clear and refresh cache when models are updated, deleted, or restored.

**How it works:**
- When enabled (default), the package listens to Eloquent's `saved` and `deleted` events
- On `saved` event: Automatically updates the cache with the new model data
- On `deleted` event: Automatically removes the model from cache
- This ensures cache is always in sync with the database

**When to disable:**
- Manual cache control needed
- Bulk operations where you want to clear cache manually
- Performance-critical applications with custom invalidation logic
- When you prefer to manually manage cache lifecycles

**Model-level override:**
```php
public function getCacheableProperties(): array
{
    return [
        'auto_invalidate' => false, // Override global setting for this model
        // ... other properties
    ];
}
```

**⚠️ Important:** When disabled, you must manually call `cacheRecord()` after updates and `forgetCache()` after deletions to keep cache in sync.

### Global Method Override

```php
'override_find_method' => env('MODEL_CACHE_OVERRIDE_FIND', false),
```

Globally override Eloquent's `find()` methods to use caching.

**⚠️ Important:** This affects all models using the trait. Use with caution.

### Relationship Caching

```php
'cache_relationships' => env('MODEL_CACHE_RELATIONSHIPS', false),
```

Cache model relationships along with the model data.

**Note:** This is experimental and may increase memory usage significantly.

### Performance Monitoring

```php
'performance_monitoring' => [
    'enabled' => env('MODEL_CACHE_PERFORMANCE_MONITORING', true),
    'hit_rate_threshold' => 0.8,
    'ttl_multiplier' => 1.5,
    'max_ttl' => 3600,
],
```

#### Performance Options

- **enabled**: Enable performance tracking and dynamic TTL adjustment
- **hit_rate_threshold**: Adjust TTL when hit rate is above this value (0.0-1.0)
- **ttl_multiplier**: Multiply TTL by this factor for high-performing cache
- **max_ttl**: Maximum TTL in seconds

### Logging Configuration

```php
'logging' => [
    'enabled' => env('MODEL_CACHE_LOGGING', false),
    'channel' => env('MODEL_CACHE_LOG_CHANNEL', null),
    'level' => env('MODEL_CACHE_LOG_LEVEL', 'debug'),
],
```

#### Logging Options

- **enabled**: Enable cache operation logging
- **channel**: Log channel to use (`null` = default channel)
- **level**: Log level (`debug`, `info`, `warning`, `error`)

#### Log Channels

Create a dedicated cache log channel in `config/logging.php`:

```php
'channels' => [
    // Other channels...
    
    'cache' => [
        'driver' => 'daily',
        'path' => storage_path('logs/cache.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
],
```

### Debug Mode

```php
'debug_mode_enabled' => env('MODEL_CACHE_DEBUG', false),
```

Enable additional debugging information and verbose logging.

**Use for:**
- Development troubleshooting
- Performance analysis
- Cache behavior debugging

## Environment Variables

### Complete .env Configuration

```env
# === Laravel Model Cache Configuration ===

# Basic Cache Settings
MODEL_CACHE_TTL=300
MODEL_CACHE_PREFIX=model-cache
MODEL_CACHE_PRIMARY_KEY=id

# Cache Driver
MODEL_CACHE_DRIVER=redis
MODEL_CACHE_STORE=null

# Behavior Settings
MODEL_CACHE_AUTO_INVALIDATE=true
MODEL_CACHE_OVERRIDE_FIND=false
MODEL_CACHE_RELATIONSHIPS=false

# Performance Monitoring
MODEL_CACHE_PERFORMANCE_MONITORING=true

# Logging
MODEL_CACHE_LOGGING=false
MODEL_CACHE_LOG_CHANNEL=cache
MODEL_CACHE_LOG_LEVEL=debug

# Debug Mode
MODEL_CACHE_DEBUG=false
```

### Environment-Specific Configurations

#### Development

```env
MODEL_CACHE_TTL=60
MODEL_CACHE_LOGGING=true
MODEL_CACHE_DEBUG=true
MODEL_CACHE_LOG_LEVEL=debug
```

#### Testing

```env
MODEL_CACHE_TTL=30
MODEL_CACHE_DRIVER=array
MODEL_CACHE_LOGGING=false
MODEL_CACHE_DEBUG=false
```

#### Production

```env
MODEL_CACHE_TTL=1800
MODEL_CACHE_DRIVER=redis
MODEL_CACHE_LOGGING=false
MODEL_CACHE_DEBUG=false
MODEL_CACHE_PERFORMANCE_MONITORING=true
```

## Model-Level Configuration

### Override Configuration Per Model

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
            'ttl' => 600, // 10 minutes for users
            'prefix' => 'users',
            'primary_key' => $this->getKeyName(),
            'auto_invalidate' => true,
            'logging' => [
                'enabled' => config('app.debug'),
                'channel' => 'cache',
                'level' => 'info',
            ],
            'driver' => 'redis',
            'cache_relationships' => false,
            'max_records_per_model' => 1000,
            'override_find_method' => false,
        ];
    }
}
```

### Conditional Configuration

```php
public function getCacheableProperties(): array
{
    $baseConfig = [
        'ttl' => 300,
        'prefix' => 'users',
        'auto_invalidate' => true,
    ];

    // Different TTL based on environment
    if (app()->environment('production')) {
        $baseConfig['ttl'] = 1800; // 30 minutes in production
    } elseif (app()->environment('testing')) {
        $baseConfig['ttl'] = 30; // 30 seconds in testing
    }

    // Enable logging in debug mode
    if (config('app.debug')) {
        $baseConfig['logging'] = [
            'enabled' => true,
            'channel' => 'cache',
            'level' => 'debug',
        ];
    }

    return $baseConfig;
}
```

## Cache Driver Configuration

### Redis Configuration

#### Basic Redis Setup

```php
// config/database.php
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
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
],
```

#### Redis Cluster Configuration

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => 'redis',
    ],
    
    'clusters' => [
        'default' => [
            [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD'),
                'port' => env('REDIS_PORT', '6379'),
                'database' => 0,
            ],
        ],
    ],
],
```

### Memcached Configuration

```php
// config/cache.php
'memcached' => [
    'driver' => 'memcached',
    'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
    'sasl' => [
        env('MEMCACHED_USERNAME'),
        env('MEMCACHED_PASSWORD'),
    ],
    'options' => [
        // Memcached::OPT_CONNECT_TIMEOUT => 2000,
    ],
    'servers' => [
        [
            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
            'port' => env('MEMCACHED_PORT', 11211),
            'weight' => 100,
        ],
    ],
],
```

## Performance Tuning

### TTL Optimization

```php
public function getCacheableProperties(): array
{
    return [
        // Frequently accessed, rarely changed data
        'ttl' => $this->isFrequentlyAccessed() ? 3600 : 300,
        
        // Dynamic TTL based on model properties
        'ttl' => $this->getOptimalTTL(),
    ];
}

private function getOptimalTTL(): int
{
    // VIP users cache longer
    if ($this->is_vip) {
        return 1800; // 30 minutes
    }
    
    // Active users cache longer
    if ($this->last_login_at && $this->last_login_at->gt(now()->subDays(7))) {
        return 900; // 15 minutes
    }
    
    return 300; // 5 minutes default
}
```

### Memory Optimization

```php
'max_records_per_model' => [
    // Limit cache size per model
    User::class => 1000,
    Product::class => 5000,
    Category::class => 100,
],
```

## Security Considerations

### Cache Key Security

```php
'prefix' => env('MODEL_CACHE_PREFIX', hash('crc32', config('app.key')) . '-models'),
```

### Sensitive Data

```php
public function shouldCache(): bool
{
    // Don't cache sensitive user data
    if ($this->hasRole('admin') || $this->has_sensitive_data) {
        return false;
    }
    
    return parent::shouldCache();
}
```

## Testing Configuration

### Test Environment Setup

```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();
    
    // Use array driver for testing
    config(['model-cache.driver' => 'array']);
    config(['model-cache.ttl' => 30]);
    config(['model-cache.logging.enabled' => false]);
}
```

### Configuration Testing

```php
public function test_configuration_is_valid()
{
    $config = config('model-cache');
    
    $this->assertIsArray($config);
    $this->assertArrayHasKey('ttl', $config);
    $this->assertIsInt($config['ttl']);
    $this->assertGreaterThan(0, $config['ttl']);
}
```

## Common Configuration Patterns

### High-Traffic Applications

```php
return [
    'ttl' => 1800, // 30 minutes
    'driver' => 'redis',
    'auto_invalidate' => true,
    'performance_monitoring' => [
        'enabled' => true,
        'hit_rate_threshold' => 0.9,
        'ttl_multiplier' => 2.0,
        'max_ttl' => 7200, // 2 hours
    ],
    'logging' => [
        'enabled' => false, // Disable in production
    ],
];
```

### Development Environment

```php
return [
    'ttl' => 60, // 1 minute
    'driver' => 'array',
    'auto_invalidate' => true,
    'performance_monitoring' => [
        'enabled' => true,
    ],
    'logging' => [
        'enabled' => true,
        'channel' => 'cache',
        'level' => 'debug',
    ],
    'debug_mode_enabled' => true,
];
```

### Microservices Architecture

```php
return [
    'prefix' => env('SERVICE_NAME', 'app') . '-models',
    'ttl' => 300,
    'driver' => 'redis',
    'logging' => [
        'enabled' => true,
        'channel' => 'microservice-cache',
    ],
];
```

## Configuration Validation

### Validate Configuration

```php
// app/Console/Commands/ValidateModelCacheConfig.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ValidateModelCacheConfig extends Command
{
    protected $signature = 'model-cache:validate-config';
    protected $description = 'Validate model cache configuration';

    public function handle()
    {
        $config = config('model-cache');
        
        $errors = [];
        
        // Validate TTL
        if (!is_int($config['ttl']) || $config['ttl'] <= 0) {
            $errors[] = 'TTL must be a positive integer';
        }
        
        // Validate driver
        if ($config['driver'] && !in_array($config['driver'], ['redis', 'memcached', 'file', 'array'])) {
            $errors[] = 'Invalid cache driver specified';
        }
        
        // Validate logging level
        $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        if ($config['logging']['enabled'] && !in_array($config['logging']['level'], $validLevels)) {
            $errors[] = 'Invalid logging level';
        }
        
        if (empty($errors)) {
            $this->info('✅ Configuration is valid');
        } else {
            $this->error('❌ Configuration errors found:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }
    }
}
```

## Troubleshooting Configuration

### Common Issues

1. **Cache not working**: Check driver configuration
2. **High memory usage**: Reduce TTL or add max_records_per_model
3. **Slow performance**: Consider using Redis instead of file cache
4. **Missing cache hits**: Check if auto_invalidate is too aggressive

### Debug Configuration

```bash
# Check current configuration
php artisan tinker
>>> config('model-cache')

# Test cache driver
>>> cache()->put('test', 'value', 60)
>>> cache()->get('test')

# Validate cache configuration
php artisan model-cache:validate-config
```
