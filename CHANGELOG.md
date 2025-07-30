# Changelog

All notable changes to `aminshamim/laravel-model-cache` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-07-30

### Added
- Initial release of Laravel Model Cache package
- `ModelCacheable` trait for automatic model caching
- `findCached()` and `findManyCached()` methods for cached model retrieval
- Automatic cache invalidation on model save/delete events
- Configurable TTL (Time To Live) for cached models
- Performance monitoring with cache hit/miss tracking
- Dynamic TTL adjustment based on performance metrics
- Query builder integration with caching capabilities
- `ModelCacheService` for cache management operations
- `ModelCache` facade for convenient access
- Cache tagging support (Redis only)
- Comprehensive logging and debugging features
- Batch cache warming and optimization
- Cache size monitoring and statistics
- Error resilience with graceful fallback to database
- Custom cache key generation support
- Conditional caching with `shouldCache()` method
- Relationship caching support
- Configuration publishing via service provider
- Full test suite with 56 tests and 205 assertions
- PHP 8.2+ compatibility
- Laravel 11.x and 12.x support
- Comprehensive documentation and API reference
- Examples and usage guides

### Features
- **Zero Configuration**: Works out of the box with sensible defaults
- **High Performance**: Optimized for speed with minimal overhead
- **Flexible**: Highly configurable per model or globally
- **Reliable**: Comprehensive test coverage and error handling
- **Developer Friendly**: Extensive logging and debugging capabilities

### Technical Details
- PSR-4 autoloading with `AminShamim\LaravelModelCache` namespace
- Service provider auto-discovery for Laravel
- Support for multiple cache drivers (Redis, Memcached, File, etc.)
- Thread-safe cache operations
- Memory efficient with configurable limits
- Extensible architecture with contracts and interfaces

### Documentation
- Complete API reference
- Installation and configuration guides
- Performance tuning recommendations
- Best practices and examples
- Troubleshooting guide

[Unreleased]: https://github.com/aminshamim/laravel-model-cache/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/aminshamim/laravel-model-cache/releases/tag/v1.0.0
