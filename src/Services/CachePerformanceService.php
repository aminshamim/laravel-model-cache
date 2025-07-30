<?php

declare(strict_types=1);

namespace AminShamim\LaravelModelCache\Services;

use AminShamim\LaravelModelCache\Contracts\CachePerformanceServiceInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CachePerformanceService implements CachePerformanceServiceInterface
{
    private const STATS_KEY_PREFIX = 'model-cache:stats:';

    private const MIN_TTL = 60;  // 1 minute

    private const MAX_TTL = 86400;  // 24 hours

    private const ADJUSTMENT_THRESHOLD = 0.5;  // 50% hit rate threshold

    private const DEFAULT_STATS_TTL = 86400; // 24 hours for stats

    public function __construct(private readonly Repository $cache) {}

    public function recordHit(string $modelClass): void
    {
        $key = $this->getStatsKey($modelClass);
        $stats = $this->getStats($modelClass);
        $stats['hits']++;
        $stats['last_hit'] = now()->timestamp;

        $this->cache->put($key, $stats, self::DEFAULT_STATS_TTL);
    }

    public function recordMiss(string $modelClass): void
    {
        $key = $this->getStatsKey($modelClass);
        $stats = $this->getStats($modelClass);
        $stats['misses']++;
        $stats['last_miss'] = now()->timestamp;

        $this->cache->put($key, $stats, self::DEFAULT_STATS_TTL);
    }

    public function getHitRate(string $modelClass): float
    {
        $stats = $this->getStats($modelClass);
        $total = $stats['hits'] + $stats['misses'];

        return $total > 0 ? round($stats['hits'] / $total, 4) : 0.0;
    }

    public function getDynamicTTL(string $modelClass, int $defaultTTL): int
    {
        $hitRate = $this->getHitRate($modelClass);

        // If hit rate is below threshold, reduce TTL
        if ($hitRate < self::ADJUSTMENT_THRESHOLD) {
            return max(self::MIN_TTL, (int) ($defaultTTL * 0.5));
        }

        // If hit rate is good, gradually increase TTL
        return min(self::MAX_TTL, (int) ($defaultTTL * 1.5));
    }

    public function resetStats(string $modelClass): void
    {
        $this->cache->forget($this->getStatsKey($modelClass));
    }

    public function getAllStats(): array
    {
        // Note: This implementation depends on the cache driver
        // For production use, consider storing model classes separately
        return [];
    }

    private function getStats(string $modelClass): array
    {
        $key = $this->getStatsKey($modelClass);

        return $this->cache->get($key, [
            'hits' => 0,
            'misses' => 0,
            'last_hit' => null,
            'last_miss' => null,
            'created_at' => now()->timestamp,
        ]);
    }

    private function getStatsKey(string $modelClass): string
    {
        return self::STATS_KEY_PREFIX.str_replace('\\', '_', $modelClass);
    }

    /**
     * Factory method for creating the service with default cache.
     */
    public static function make(?Repository $cache = null): self
    {
        return new self($cache ?? Cache::store());
    }
}
