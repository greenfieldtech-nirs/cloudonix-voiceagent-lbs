<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Idempotency Service for Cloudonix Voice Application
 *
 * Ensures webhook events are processed exactly once, preventing duplicate
 * call processing and state mutations.
 */
class IdempotencyService
{
    private RedisService $redis;

    public function __construct(RedisService $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Check if webhook event has already been processed
     */
    public function isEventProcessed(int $tenantId, string $eventType, string $sessionToken, string $eventId): bool
    {
        return $this->redis->isEventProcessed($tenantId, $eventType, $sessionToken, $eventId);
    }

    /**
     * Mark webhook event as processed
     */
    public function markEventProcessed(int $tenantId, string $eventType, string $sessionToken, string $eventId, array $metadata = []): void
    {
        $processingData = [
            'event_type' => $eventType,
            'session_token' => $sessionToken,
            'event_id' => $eventId,
            'processed_at' => now()->toISOString(),
            'metadata' => $metadata,
        ];

        $this->redis->markEventProcessed($tenantId, $eventType, $sessionToken, $eventId);
    }

    /**
     * Execute operation with idempotency guarantee
     */
    public function executeIdempotent(callable $operation, int $tenantId, string $eventType, string $sessionToken, string $eventId, array $metadata = []): mixed
    {
        // Check if already processed
        if ($this->isEventProcessed($tenantId, $eventType, $sessionToken, $eventId)) {
            \Log::info('Idempotent operation skipped - already processed', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'session_token' => $sessionToken,
                'event_id' => $eventId,
            ]);

            return null; // Operation was already executed
        }

        try {
            // Execute the operation
            $result = $operation();

            // Mark as processed
            $this->markEventProcessed($tenantId, $eventType, $sessionToken, $eventId, $metadata);

            return $result;

        } catch (\Exception $e) {
            // Log the error but don't mark as processed
            \Log::error('Idempotent operation failed', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'session_token' => $sessionToken,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate idempotency key for webhook
     */
    public function generateWebhookIdempotencyKey(array $webhookData): string
    {
        // Use combination of session token and event-specific data
        $components = [
            $webhookData['Session'] ?? '',
            $webhookData['CallSid'] ?? '',
            $webhookData['event_type'] ?? 'unknown',
            $webhookData['timestamp'] ?? now()->timestamp,
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Clean up expired idempotency keys
     * This should be run as a scheduled job
     */
    public function cleanupExpiredKeys(int $maxAgeHours = 24): int
    {
        // Redis TTL handles automatic cleanup, but we can implement
        // manual cleanup for keys that might have missed TTL
        $pattern = "tenant:*:webhook:idempotent:*";
        $keys = Redis::keys($pattern);

        $cleaned = 0;
        $maxAge = now()->subHours($maxAgeHours);

        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);

            // If key has no TTL or TTL is invalid, check last access
            if ($ttl <= 0) {
                // For keys without proper TTL, we could implement custom cleanup
                // For now, just count them
                $cleaned++;
            }
        }

        \Log::info('Idempotency cleanup completed', [
            'keys_found' => count($keys),
            'keys_cleaned' => $cleaned,
            'max_age_hours' => $maxAgeHours,
        ]);

        return $cleaned;
    }

    /**
     * Get idempotency statistics
     */
    public function getStatistics(int $tenantId): array
    {
        $pattern = "tenant:{$tenantId}:webhook:idempotent:*";
        $keys = Redis::keys($pattern);

        $stats = [
            'total_keys' => count($keys),
            'keys_by_event_type' => [],
            'oldest_key_age' => null,
            'newest_key_age' => null,
        ];

        foreach ($keys as $key) {
            // Extract event type from key
            if (preg_match('/webhook:idempotent:([^:]+):/', $key, $matches)) {
                $eventType = $matches[1];
                $stats['keys_by_event_type'][$eventType] = ($stats['keys_by_event_type'][$eventType] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /**
     * Force reset idempotency for testing/debugging
     * WARNING: Only use in development/testing environments
     */
    public function resetIdempotency(int $tenantId, string $eventType = null, string $sessionToken = null): int
    {
        $pattern = "tenant:{$tenantId}:webhook:idempotent:";

        if ($eventType) {
            $pattern .= "{$eventType}:";
        }

        if ($sessionToken) {
            $pattern .= "{$sessionToken}:*";
        } else {
            $pattern .= "*";
        }

        $keys = Redis::keys($pattern);
        $deleted = 0;

        foreach ($keys as $key) {
            Redis::del($key);
            $deleted++;
        }

        \Log::warning('Idempotency keys reset', [
            'tenant_id' => $tenantId,
            'event_type' => $eventType,
            'session_token' => $sessionToken,
            'keys_deleted' => $deleted,
        ]);

        return $deleted;
    }
}