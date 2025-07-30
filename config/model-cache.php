<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Cache Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for the model cache package.
    | You can override these settings per model using the getCacheableProperties method.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | How long should individual model records be cached (in seconds)?
    | This can be dynamically adjusted based on cache performance.
    |
    */
    'ttl' => (int) env('MODEL_CACHE_TTL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | Cache prefix for model records. This helps avoid key collisions
    | with other cache entries in your application.
    |
    */
    'prefix' => env('MODEL_CACHE_PREFIX', 'model-cache'),

    /*
    |--------------------------------------------------------------------------
    | Primary Key Field
    |--------------------------------------------------------------------------
    |
    | Default primary key field name. This will be overridden by each model's
    | primary key configuration automatically.
    |
    */
    'primary_key' => 'id',

    /*
    |--------------------------------------------------------------------------
    | Auto Cache Invalidation
    |--------------------------------------------------------------------------
    |
    | Whether to enable automatic cache invalidation on model events
    | (save, update, delete). Set to false to handle manually.
    |
    */
    'auto_invalidate' => (bool) env('MODEL_CACHE_AUTO_INVALIDATE', true),

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for cache operations. Useful for debugging
    | and monitoring cache performance.
    |
    */
    'logging' => [
        'enabled' => (bool) env('MODEL_CACHE_LOGGING_ENABLED', false),
        'channel' => env('MODEL_CACHE_LOGGING_CHANNEL'), // null = use default channel
        'level' => env('MODEL_CACHE_LOGGING_LEVEL', 'debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Driver
    |--------------------------------------------------------------------------
    |
    | Cache driver to use. If null, uses the default cache driver.
    | Recommended: 'redis' for production, 'array' for testing.
    |
    */
    'driver' => env('MODEL_CACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Cache Relationships
    |--------------------------------------------------------------------------
    |
    | Whether to cache loaded relationships along with models.
    | This can improve performance but increases memory usage.
    |
    */
    'cache_relationships' => (bool) env('MODEL_CACHE_RELATIONSHIPS', false),

    /*
    |--------------------------------------------------------------------------
    | Maximum Records Per Model
    |--------------------------------------------------------------------------
    |
    | Maximum number of cached records per model class.
    | Set to 0 for unlimited. Helps prevent memory issues.
    |
    */
    'max_records_per_model' => (int) env('MODEL_CACHE_MAX_RECORDS', 0),

    /*
    |--------------------------------------------------------------------------
    | Override Find Method
    |--------------------------------------------------------------------------
    |
    | Whether to automatically override the default find() method with caching.
    | If false, you need to use findCached() explicitly.
    |
    */
    'override_find_method' => (bool) env('MODEL_CACHE_OVERRIDE_FIND', false),

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode for detailed logging and debugging information.
    | Only enable in development environments.
    |
    */
    'debug_mode_enabled' => (bool) env('MODEL_CACHE_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Cache Compression
    |--------------------------------------------------------------------------
    |
    | Enable compression for cached data to reduce memory usage.
    | Requires additional CPU overhead for compression/decompression.
    |
    */
    'compression' => [
        'enabled' => (bool) env('MODEL_CACHE_COMPRESSION', false),
        'algorithm' => env('MODEL_CACHE_COMPRESSION_ALGORITHM', 'gzip'), // gzip, deflate, bzip2
        'level' => (int) env('MODEL_CACHE_COMPRESSION_LEVEL', 6), // 1-9 for gzip
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Configure performance monitoring and dynamic TTL adjustments.
    |
    */
    'performance' => [
        'enabled' => (bool) env('MODEL_CACHE_PERFORMANCE_MONITORING', true),
        'min_ttl' => (int) env('MODEL_CACHE_MIN_TTL', 60), // 1 minute
        'max_ttl' => (int) env('MODEL_CACHE_MAX_TTL', 86400), // 24 hours
        'adjustment_threshold' => (float) env('MODEL_CACHE_ADJUSTMENT_THRESHOLD', 0.5), // 50% hit rate
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Tags Support
    |--------------------------------------------------------------------------
    |
    | Enable Redis tags for better cache management and invalidation.
    | Only works with Redis cache driver.
    |
    */
    'redis_tags' => (bool) env('MODEL_CACHE_REDIS_TAGS', true),
];
