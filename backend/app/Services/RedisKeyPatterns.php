<?php

namespace App\Services;

/**
 * Redis Key Patterns for Cloudonix Voice Application Tool
 *
 * This class defines all Redis key patterns and data structures used
 * for load balancing, session management, and distributed operations.
 */
class RedisKeyPatterns
{
    /**
     * Key prefix for tenant isolation
     */
    public const TENANT_PREFIX = 'tenant:{tenant_id}';

    /**
     * Load Balancing Keys
     * Used to track agent call counts within rolling time windows
     */
    public const LOAD_BALANCED_CALLS = self::TENANT_PREFIX . ':group:{group_id}:load_balanced:calls';
    // Type: Sorted Set
    // Members: agent_id (score: call count in window)
    // TTL: None (persisted until reset)

    public const LOAD_BALANCED_WINDOW = self::TENANT_PREFIX . ':group:{group_id}:load_balanced:window';
    // Type: Sorted Set
    // Members: timestamp:agent_id (score: unix timestamp)
    // TTL: None (managed by cleanup process)

    /**
     * Round Robin Keys
     * Used to maintain rotation state for round-robin distribution
     */
    public const ROUND_ROBIN_CURRENT = self::TENANT_PREFIX . ':group:{group_id}:round_robin:current';
    // Type: String
    // Value: current agent_id in rotation
    // TTL: None (persisted across restarts)

    /**
     * Distributed Lock Keys
     * Used for atomic operations and preventing race conditions
     */
    public const ROUTING_LOCK = self::TENANT_PREFIX . ':routing:lock:{session_token}';
    // Type: String (SET with NX EX options)
    // Value: lock holder identifier (worker ID)
    // TTL: 30 seconds (configurable)

    public const GROUP_UPDATE_LOCK = self::TENANT_PREFIX . ':group:{group_id}:update_lock';
    // Type: String
    // Value: lock holder identifier
    // TTL: 10 seconds

    /**
     * Idempotency Keys
     * Used to prevent duplicate webhook processing
     */
    public const WEBHOOK_IDEMPOTENT = self::TENANT_PREFIX . ':webhook:idempotent:{event_type}:{session_token}:{event_id}';
    // Type: String
    // Value: processing_status ('processing', 'completed')
    // TTL: 24 hours (86400 seconds)

    /**
     * Session State Keys
     * Used to track call session state and transitions
     */
    public const SESSION_STATE = self::TENANT_PREFIX . ':session:{session_token}:state';
    // Type: Hash
    // Fields: current_state, start_time, agent_id, group_id, metadata
    // TTL: 24 hours (or until session ends)

    public const SESSION_EVENTS = self::TENANT_PREFIX . ':session:{session_token}:events';
    // Type: List
    // Values: JSON event objects
    // TTL: 24 hours

    /**
     * Real-Time Event Broadcasting Keys
     */
    public const EVENTS_CHANNEL = self::TENANT_PREFIX . ':events';
    // Redis Pub/Sub Channel
    // Messages: JSON event payloads

    public const METRICS_CACHE = self::TENANT_PREFIX . ':metrics:dashboard';
    // Type: Hash
    // Fields: calls_today, success_rate, active_calls, etc.
    // TTL: 5 minutes

    /**
     * Cache Keys
     */
    public const ROUTING_RULES_CACHE = self::TENANT_PREFIX . ':cache:routing_rules';
    // Type: String (JSON)
    // Value: Serialized routing rules array
    // TTL: 5 minutes

    public const AGENT_STATUS_CACHE = self::TENANT_PREFIX . ':cache:agent_status:{agent_id}';
    // Type: String
    // Value: 'enabled' or 'disabled'
    // TTL: 1 minute

    /**
     * Utility Methods
     */
    public static function getTenantPrefix(int $tenantId): string
    {
        return str_replace('{tenant_id}', $tenantId, self::TENANT_PREFIX);
    }

    public static function getLoadBalancedCallsKey(int $tenantId, int $groupId): string
    {
        return str_replace(
            ['{tenant_id}', '{group_id}'],
            [$tenantId, $groupId],
            self::LOAD_BALANCED_CALLS
        );
    }

    public static function getRoundRobinCurrentKey(int $tenantId, int $groupId): string
    {
        return str_replace(
            ['{tenant_id}', '{group_id}'],
            [$tenantId, $groupId],
            self::ROUND_ROBIN_CURRENT
        );
    }

    public static function getRoutingLockKey(int $tenantId, string $sessionToken): string
    {
        return str_replace(
            ['{tenant_id}', '{session_token}'],
            [$tenantId, $sessionToken],
            self::ROUTING_LOCK
        );
    }

    public static function getIdempotencyKey(int $tenantId, string $eventType, string $sessionToken, string $eventId): string
    {
        return str_replace(
            ['{tenant_id}', '{event_type}', '{session_token}', '{event_id}'],
            [$tenantId, $eventType, $sessionToken, $eventId],
            self::WEBHOOK_IDEMPOTENT
        );
    }

    public static function getSessionStateKey(int $tenantId, string $sessionToken): string
    {
        return str_replace(
            ['{tenant_id}', '{session_token}'],
            [$tenantId, $sessionToken],
            self::SESSION_STATE
        );
    }

    public static function getEventsChannel(int $tenantId): string
    {
        return str_replace('{tenant_id}', $tenantId, self::EVENTS_CHANNEL);
    }
}