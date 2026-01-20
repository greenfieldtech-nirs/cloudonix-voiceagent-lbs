<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Redis Service for Cloudonix Voice Application Tool
 *
 * Provides high-level methods for working with Redis data structures
 * used in load balancing, session management, and distributed operations.
 */
class RedisService
{
    /**
     * Load Balancing Operations
     */

    /**
     * Record a call for load balancing tracking
     */
    public function recordLoadBalancedCall(int $tenantId, int $groupId, int $agentId, int $windowHours = 24): void
    {
        $callsKey = RedisKeyPatterns::getLoadBalancedCallsKey($tenantId, $groupId);
        $windowKey = RedisKeyPatterns::getLoadBalancedWindowKey($tenantId, $groupId);

        $timestamp = now()->timestamp;
        $windowStart = now()->subHours($windowHours)->timestamp;

        // Add call to current counts
        Redis::zincrby($callsKey, 1, $agentId);

        // Add timestamp entry for cleanup
        Redis::zadd($windowKey, $timestamp, "{$timestamp}:{$agentId}");

        // Clean old entries
        Redis::zremrangebyscore($windowKey, 0, $windowStart);

        // Update counts based on remaining window entries
        $this->updateLoadBalancedCounts($tenantId, $groupId, $windowStart);
    }

    /**
     * Get load balanced agent selection
     */
    public function getLeastLoadedAgent(int $tenantId, int $groupId): ?int
    {
        $callsKey = RedisKeyPatterns::getLoadBalancedCallsKey($tenantId, $groupId);

        // Get agent with lowest score (fewest calls)
        $result = Redis::zrange($callsKey, 0, 0, 'WITHSCORES');

        if (empty($result)) {
            return null;
        }

        return (int) key($result);
    }

    /**
     * Update load balanced counts based on time window
     */
    private function updateLoadBalancedCounts(int $tenantId, int $groupId, int $windowStart): void
    {
        $callsKey = RedisKeyPatterns::getLoadBalancedCallsKey($tenantId, $groupId);
        $windowKey = RedisKeyPatterns::getLoadBalancedWindowKey($tenantId, $groupId);

        // Get all agents currently in calls
        $currentAgents = Redis::zrange($callsKey, 0, -1);

        // Reset counts
        if (!empty($currentAgents)) {
            Redis::zrem($callsKey, ...$currentAgents);
        }

        // Recalculate from window entries
        $windowEntries = Redis::zrange($windowKey, 0, -1, 'WITHSCORES');

        $agentCounts = [];
        foreach ($windowEntries as $entry => $timestamp) {
            if ($timestamp >= $windowStart) {
                [$timestamp, $agentId] = explode(':', $entry);
                $agentCounts[$agentId] = ($agentCounts[$agentId] ?? 0) + 1;
            }
        }

        // Update Redis with new counts
        foreach ($agentCounts as $agentId => $count) {
            Redis::zadd($callsKey, $count, $agentId);
        }
    }

    /**
     * Round Robin Operations
     */

    /**
     * Get next agent in round-robin rotation
     */
    public function getNextRoundRobinAgent(int $tenantId, int $groupId, array $enabledAgentIds): ?int
    {
        $currentKey = RedisKeyPatterns::getRoundRobinCurrentKey($tenantId, $groupId);

        $currentAgentId = Redis::get($currentKey);

        if (!$currentAgentId || !in_array((int)$currentAgentId, $enabledAgentIds)) {
            // Start with first agent or reset if current is invalid
            $nextAgentId = $enabledAgentIds[0] ?? null;
        } else {
            // Find next agent in rotation
            $currentIndex = array_search((int)$currentAgentId, $enabledAgentIds);
            $nextIndex = ($currentIndex + 1) % count($enabledAgentIds);
            $nextAgentId = $enabledAgentIds[$nextIndex];
        }

        if ($nextAgentId) {
            Redis::set($currentKey, $nextAgentId);
        }

        return $nextAgentId;
    }

    /**
     * Distributed Lock Operations
     */

    /**
     * Acquire a distributed lock
     */
    public function acquireLock(string $lockKey, string $ownerId, int $ttlSeconds = 30): bool
    {
        return Redis::set($lockKey, $ownerId, 'NX', 'EX', $ttlSeconds);
    }

    /**
     * Release a distributed lock
     */
    public function releaseLock(string $lockKey, string $ownerId): bool
    {
        $script = "
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        ";

        return Redis::eval($script, 1, $lockKey, $ownerId) === 1;
    }

    /**
     * Idempotency Operations
     */

    /**
     * Check if webhook event was already processed
     */
    public function isEventProcessed(int $tenantId, string $eventType, string $sessionToken, string $eventId): bool
    {
        $key = RedisKeyPatterns::getIdempotencyKey($tenantId, $eventType, $sessionToken, $eventId);
        return Redis::exists($key);
    }

    /**
     * Mark webhook event as processed
     */
    public function markEventProcessed(int $tenantId, string $eventType, string $sessionToken, string $eventId, int $ttlHours = 24): void
    {
        $key = RedisKeyPatterns::getIdempotencyKey($tenantId, $eventType, $sessionToken, $eventId);
        Redis::setex($key, $ttlHours * 3600, 'completed');
    }

    /**
     * Session State Operations
     */

    /**
     * Get session state
     */
    public function getSessionState(int $tenantId, string $sessionToken): ?array
    {
        $key = RedisKeyPatterns::getSessionStateKey($tenantId, $sessionToken);
        $data = Redis::hgetall($key);

        return empty($data) ? null : $data;
    }

    /**
     * Update session state
     */
    public function updateSessionState(int $tenantId, string $sessionToken, array $state, int $ttlHours = 24): void
    {
        $key = RedisKeyPatterns::getSessionStateKey($tenantId, $sessionToken);

        Redis::hmset($key, $state);
        Redis::expire($key, $ttlHours * 3600);
    }

    /**
     * Clear session state
     */
    public function clearSessionState(int $tenantId, string $sessionToken): void
    {
        $key = RedisKeyPatterns::getSessionStateKey($tenantId, $sessionToken);
        Redis::del($key);
    }

    /**
     * Real-Time Event Operations
     */

    /**
     * Publish real-time event
     */
    public function publishEvent(int $tenantId, string $eventType, array $data): void
    {
        $channel = RedisKeyPatterns::getEventsChannel($tenantId);

        $event = [
            'type' => $eventType,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        Redis::publish($channel, json_encode($event));
    }

    /**
     * Cache Operations
     */

    /**
     * Get cached value
     */
    public function getCache(string $key): ?string
    {
        return Redis::get($key);
    }

    /**
     * Set cached value with TTL
     */
    public function setCache(string $key, string $value, int $ttlSeconds): void
    {
        Redis::setex($key, $ttlSeconds, $value);
    }

    /**
     * Delete cached value
     */
    public function deleteCache(string $key): void
    {
        Redis::del($key);
    }
}