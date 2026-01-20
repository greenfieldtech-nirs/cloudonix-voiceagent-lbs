# Redis Architecture for Cloudonix Voice Application Tool

## Overview

This document describes the Redis data structures and key patterns used for load balancing, session management, and distributed operations in the Cloudonix Voice Application Tool.

## Key Patterns

### 1. Load Balancing Counters

**Purpose**: Track agent call counts within rolling time windows for load balanced distribution.

**Keys**:
```
tenant:{tenant_id}:group:{group_id}:load_balanced:calls
tenant:{tenant_id}:group:{group_id}:load_balanced:window
```

**Data Structures**:
- `calls`: Sorted Set where member = agent_id, score = call count
- `window`: Sorted Set where member = "timestamp:agent_id", score = unix timestamp

**Operations**:
- Record call: `ZINCRBY` on calls key, `ZADD` timestamp to window
- Get least loaded: `ZRANGE` with `WITHSCORES` to find lowest score
- Cleanup: `ZREMRANGEBYSCORE` to remove old entries

### 2. Round Robin Pointers

**Purpose**: Maintain rotation state for round-robin distribution.

**Key**: `tenant:{tenant_id}:group:{group_id}:round_robin:current`

**Data Structure**: String containing current agent_id

**Operations**:
- Get current: `GET` key
- Update next: `SET` key with next agent_id
- Persisted across restarts

### 3. Distributed Locks

**Purpose**: Prevent race conditions in routing decisions and group updates.

**Keys**:
```
tenant:{tenant_id}:routing:lock:{session_token}
tenant:{tenant_id}:group:{group_id}:update_lock
```

**Data Structure**: String with lock owner identifier

**Operations**:
- Acquire: `SET key owner NX EX 30`
- Release: Lua script to check owner before delete
- TTL: 30 seconds for routing, 10 seconds for updates

### 4. Idempotency Keys

**Purpose**: Prevent duplicate webhook processing.

**Key**: `tenant:{tenant_id}:webhook:idempotent:{event_type}:{session_token}:{event_id}`

**Data Structure**: String with status ("processing", "completed")

**Operations**:
- Check: `EXISTS` key
- Mark: `SETEX` key with 24-hour TTL
- Automatic cleanup via TTL

### 5. Session State

**Purpose**: Track call session state and transitions.

**Key**: `tenant:{tenant_id}:session:{session_token}:state`

**Data Structure**: Hash with fields:
- `current_state`: Current state enum
- `start_time`: Session start timestamp
- `agent_id`: Assigned agent ID
- `group_id`: Assigned group ID
- `metadata`: Additional session data

**Operations**:
- Get state: `HGETALL`
- Update state: `HMSET` + `EXPIRE`
- TTL: 24 hours

### 6. Real-Time Events

**Purpose**: Broadcast live updates to connected clients.

**Channel**: `tenant:{tenant_id}:events`

**Message Format**:
```json
{
  "type": "call_started|call_completed|agent_status_changed|metrics_updated",
  "data": { /* event-specific data */ },
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Operations**:
- Publish: `PUBLISH` to channel
- Subscribe: Client connections subscribe to tenant channel

### 7. Cache Keys

**Purpose**: Cache frequently accessed data for performance.

**Keys**:
```
tenant:{tenant_id}:metrics:dashboard
tenant:{tenant_id}:cache:routing_rules
tenant:{tenant_id}:cache:agent_status:{agent_id}
```

**Data Structures**:
- `metrics`: Hash with dashboard metrics
- `routing_rules`: JSON string of rules array
- `agent_status`: String status ("enabled"/"disabled")

**TTL Strategy**:
- Metrics: 5 minutes
- Routing rules: 5 minutes
- Agent status: 1 minute

## Usage Examples

### Load Balancing

```php
// Record a call for agent 123 in group 456
$redis->recordLoadBalancedCall(1, 456, 123);

// Get least loaded agent
$agentId = $redis->getLeastLoadedAgent(1, 456);
```

### Round Robin

```php
// Get next agent in rotation for group 456
$agentId = $redis->getNextRoundRobinAgent(1, 456, [123, 124, 125]);
```

### Distributed Locks

```php
$lockKey = RedisKeyPatterns::getRoutingLockKey(1, 'session_123');

// Acquire lock
if ($redis->acquireLock($lockKey, 'worker_1', 30)) {
    try {
        // Critical section
        performRouting();
    } finally {
        $redis->releaseLock($lockKey, 'worker_1');
    }
}
```

### Idempotency

```php
// Check if webhook already processed
if (!$redis->isEventProcessed(1, 'voice.application.request', 'session_123', 'event_456')) {
    // Process webhook
    processWebhook();

    // Mark as processed
    $redis->markEventProcessed(1, 'voice.application.request', 'session_123', 'event_456');
}
```

## Performance Considerations

### Memory Management
- Use TTL for automatic cleanup of temporary data
- Implement background cleanup for time-windowed data
- Monitor memory usage with Redis INFO command

### Connection Pooling
- Use connection pooling to avoid connection overhead
- Configure appropriate pool sizes for concurrent operations
- Implement retry logic for connection failures

### Monitoring
- Track Redis command latency with SLOWLOG
- Monitor memory usage and key counts
- Set up alerts for high memory usage or connection errors

## Scaling Considerations

### Horizontal Scaling
- Use Redis Cluster for multi-node deployments
- Implement hash tags for related keys
- Design for eventual consistency where appropriate

### High Availability
- Configure Redis Sentinel for automatic failover
- Implement retry logic with exponential backoff
- Design fallback mechanisms for Redis unavailability

### Data Persistence
- Configure RDB/AOF persistence based on requirements
- Implement backup strategies for critical data
- Plan for data recovery procedures