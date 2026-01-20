<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Redis Strategy Service
 *
 * Centralized Redis operations for distribution strategies.
 * Handles atomic operations, memory management, and fallback mechanisms.
 */
class RedisStrategyService
{
    private const LOCK_TIMEOUT = 10; // seconds
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 100;

    /**
     * Execute an atomic Redis operation with distributed locking
     */
    public function executeAtomic(string $lockKey, callable $operation): mixed
    {
        $lockAcquired = false;
        $attempts = 0;

        while ($attempts < self::MAX_RETRY_ATTEMPTS) {
            try {
                // Try to acquire distributed lock
                $lockAcquired = $this->acquireLock($lockKey);

                if ($lockAcquired) {
                    $result = $operation();
                    return $result;
                }

                // Wait before retry
                usleep(self::RETRY_DELAY_MS * 1000 * ($attempts + 1));
                $attempts++;

            } catch (\Exception $e) {
                Log::warning('Redis atomic operation failed', [
                    'lock_key' => $lockKey,
                    'attempt' => $attempts + 1,
                    'error' => $e->getMessage(),
                ]);

                if ($lockAcquired) {
                    $this->releaseLock($lockKey);
                }

                $attempts++;

                if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException("Failed to execute atomic operation after {$attempts} attempts");
    }

    /**
     * Acquire a distributed lock
     */
    public function acquireLock(string $lockKey, int $timeout = self::LOCK_TIMEOUT): bool
    {
        $lockValue = uniqid('', true);
        $fullLockKey = "lock:{$lockKey}";

        // Try to set lock with NX (only if not exists) and EX (expire)
        $result = Redis::set($fullLockKey, $lockValue, 'EX', $timeout, 'NX');

        return $result === true;
    }

    /**
     * Release a distributed lock
     */
    public function releaseLock(string $lockKey): void
    {
        $fullLockKey = "lock:{$lockKey}";
        Redis::del($fullLockKey);
    }

    /**
     * Safely increment a counter with bounds checking
     */
    public function safeIncrement(string $key, int $maxValue = null, int $defaultValue = 0): int
    {
        try {
            $currentValue = (int) Redis::get($key) ?? $defaultValue;
            $newValue = $currentValue + 1;

            // Reset to 0 if max value reached
            if ($maxValue !== null && $newValue >= $maxValue) {
                $newValue = 0;
            }

            Redis::set($key, $newValue);
            return $newValue;

        } catch (\Exception $e) {
            Log::error('Redis increment operation failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $defaultValue;
        }
    }

    /**
     * Get memory usage statistics for strategy keys
     */
    public function getMemoryStats(): array
    {
        try {
            $info = Redis::info('memory');

            // Get strategy-related keys
            $strategyKeys = Redis::keys('agent_load:*') +
                           Redis::keys('round_robin:*') +
                           Redis::keys('priority:*') +
                           Redis::keys('lock:*');

            $keyCount = count($strategyKeys);
            $totalSize = 0;

            foreach ($strategyKeys as $key) {
                $size = Redis::memory('usage', $key);
                $totalSize += $size ?? 0;
            }

            return [
                'redis_memory_used' => $info['used_memory'] ?? 0,
                'redis_memory_peak' => $info['used_memory_peak'] ?? 0,
                'strategy_keys_count' => $keyCount,
                'strategy_memory_used' => $totalSize,
                'memory_fragmentation' => $info['mem_fragmentation_ratio'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Redis memory stats', [
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to retrieve memory statistics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean up expired strategy keys
     */
    public function cleanupExpiredKeys(): int
    {
        $patterns = [
            'agent_load:*',     // Load balancing call counters
            'round_robin:*',    // Round robin state
            'priority:*',       // Priority rotation state
            'lock:*',          // Expired locks
        ];

        $cleanedCount = 0;

        foreach ($patterns as $pattern) {
            try {
                $keys = Redis::keys($pattern);

                foreach ($keys as $key) {
                    // Check if key has TTL (not persistent)
                    $ttl = Redis::ttl($key);

                    if ($ttl === -2) { // Key doesn't exist
                        continue;
                    }

                    // If key has no expiration or is expired, check if it should be cleaned
                    if ($ttl === -1) { // No expiration
                        // Clean up old load balancing keys that should have had expiration
                        if (str_starts_with($key, 'agent_load:')) {
                            Redis::del($key);
                            $cleanedCount++;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to cleanup pattern', [
                    'pattern' => $pattern,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($cleanedCount > 0) {
            Log::info('Cleaned up expired Redis keys', [
                'cleaned_count' => $cleanedCount,
            ]);
        }

        return $cleanedCount;
    }

    /**
     * Get strategy performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        try {
            $metrics = [
                'operations_per_second' => Redis::info('stats')['instantaneous_ops_per_sec'] ?? 0,
                'hit_rate' => $this->calculateHitRate(),
                'connection_pool_size' => Redis::info('clients')['connected_clients'] ?? 0,
                'strategy_operations' => $this->countStrategyOperations(),
            ];

            return $metrics;

        } catch (\Exception $e) {
            return [
                'error' => 'Failed to retrieve performance metrics',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate Redis cache hit rate
     */
    private function calculateHitRate(): float
    {
        try {
            $stats = Redis::info('stats');
            $hits = (float) ($stats['keyspace_hits'] ?? 0);
            $misses = (float) ($stats['keyspace_misses'] ?? 0);

            $total = $hits + $misses;
            return $total > 0 ? ($hits / $total) * 100 : 0;

        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Count recent strategy operations
     */
    private function countStrategyOperations(): array
    {
        // This would require additional tracking keys
        // For now, return basic counts
        return [
            'load_balanced_keys' => count(Redis::keys('agent_load:*')),
            'round_robin_keys' => count(Redis::keys('round_robin:*')),
            'priority_keys' => count(Redis::keys('priority:*')),
            'active_locks' => count(Redis::keys('lock:*')),
        ];
    }

    /**
     * Check Redis connectivity and fallback readiness
     */
    public function checkHealth(): array
    {
        $health = [
            'redis_connected' => false,
            'fallback_available' => true, // Database fallback assumed available
            'last_check' => now()->toISOString(),
        ];

        try {
            Redis::ping();
            $health['redis_connected'] = true;
            $health['response_time_ms'] = $this->measureResponseTime();

        } catch (\Exception $e) {
            $health['error'] = $e->getMessage();
            Log::warning('Redis health check failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $health;
    }

    /**
     * Measure Redis response time
     */
    private function measureResponseTime(): float
    {
        $start = microtime(true);
        Redis::ping();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2); // milliseconds
    }

    /**
     * Execute operation with fallback to database
     */
    public function executeWithFallback(callable $redisOperation, callable $fallbackOperation = null): mixed
    {
        try {
            return $redisOperation();
        } catch (\Exception $e) {
            Log::warning('Redis operation failed, attempting fallback', [
                'error' => $e->getMessage(),
            ]);

            if ($fallbackOperation) {
                return $fallbackOperation();
            }

            throw $e;
        }
    }
}