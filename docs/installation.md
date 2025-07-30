# Installation Guide

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- One of the following cache drivers:
  - Redis (recommended for production)
  - Memcached
  - Array (for testing)
  - File (not recommended for production)

## Step-by-Step Installation

### 1. Install via Composer

```bash
composer require aminshamim/laravel-model-cache
```

### 2. Service Provider Registration

The service provider will be automatically registered via Laravel's package discovery. If you need to register it manually, add it to your `config/app.php`:

```php
'providers' => [
    // Other providers...
    AminShamim\ModelCache\ModelCacheServiceProvider::class,
],
```

### 3. Facade Registration (Optional)

If you want to use the facade, add it to your `config/app.php`:

```php
'aliases' => [
    // Other aliases...
    'ModelCache' => AminShamim\ModelCache\Facades\ModelCache::class,
],
```

### 4. Publish Configuration

Publish the configuration file to customize the package settings:

```bash
php artisan vendor:publish --tag=model-cache-config
```

This will create a `config/model-cache.php` file where you can customize all package settings.

### 5. Environment Configuration

Add the following environment variables to your `.env` file:

```env
# Cache Configuration
MODEL_CACHE_TTL=300
MODEL_CACHE_PREFIX=model-cache
MODEL_CACHE_AUTO_INVALIDATE=true
MODEL_CACHE_DRIVER=redis

# Performance Monitoring
MODEL_CACHE_PERFORMANCE_MONITORING=true

# Logging
MODEL_CACHE_LOGGING=false
MODEL_CACHE_LOG_CHANNEL=null
MODEL_CACHE_LOG_LEVEL=debug

# Debug Mode
MODEL_CACHE_DEBUG=false

# Global Overrides
MODEL_CACHE_OVERRIDE_FIND=false
MODEL_CACHE_RELATIONSHIPS=false
```

### 6. Cache Driver Setup

#### Redis (Recommended)

Make sure Redis is installed and configured in your `config/database.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
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

#### Memcached

Install Memcached and configure it in your `config/cache.php`:

```php
'memcached' => [
    'driver' => 'memcached',
    'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
    'sasl' => [
        env('MEMCACHED_USERNAME'),
        env('MEMCACHED_PASSWORD'),
    ],
    'options' => [
        // Add any Memcached options here
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

### 7. Verify Installation

Create a simple test to verify the installation:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use AminShamim\ModelCache\Services\ModelCacheService;
use Tests\TestCase;

class ModelCacheInstallationTest extends TestCase
{
    public function test_model_cache_is_working()
    {
        $user = User::factory()->create();
        
        // Test caching functionality
        $this->assertTrue($user->cacheRecord());
        
        // Test finding cached record
        $cachedUser = User::findCached($user->id);
        $this->assertEquals($user->id, $cachedUser->id);
        
        // Test cache service
        $stats = ModelCacheService::getCacheStats(User::class);
        $this->assertIsArray($stats);
        
        echo "✅ Laravel Model Cache is installed and working correctly!\n";
    }
}
```

Run the test:

```bash
php artisan test --filter=ModelCacheInstallationTest
```

## Troubleshooting

### Common Issues

#### 1. Service Provider Not Found

If you get an error about the service provider not being found, make sure you have the correct package name and version:

```bash
composer show aminshamim/laravel-model-cache
```

#### 2. Cache Driver Issues

If you're having cache driver issues, verify your cache configuration:

```bash
php artisan cache:clear
php artisan config:clear
php artisan config:cache
```

#### 3. Redis Connection Issues

Test your Redis connection:

```bash
redis-cli ping
```

Should return `PONG`.

#### 4. Permission Issues

Make sure your cache directory is writable:

```bash
chmod -R 775 storage/framework/cache
```

### Debugging

Enable debug mode to get more detailed error messages:

```env
MODEL_CACHE_DEBUG=true
MODEL_CACHE_LOGGING=true
MODEL_CACHE_LOG_LEVEL=debug
```

Then check your logs:

```bash
tail -f storage/logs/laravel.log
```

## Next Steps

1. [Configure the package](configuration.md) according to your needs
2. [Add the trait to your models](usage.md)
3. [Learn about performance optimization](performance.md)
4. [Explore advanced features](api.md)

## Support

If you encounter any issues during installation:

1. Check the [troubleshooting section](#troubleshooting)
2. Review the [configuration guide](configuration.md)
3. Create an issue on the GitHub repository
4. Check existing issues for similar problems
