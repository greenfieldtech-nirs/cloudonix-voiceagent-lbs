# Cloudonix Voice Application Tool - Product Specification Plan

## Executive Summary

This document outlines the technical architecture, API specifications, data models, and implementation details for the Cloudonix Voice Application Tool - a load distribution and routing system for AI Voice Agents built on Laravel (PHP), React SPA, MySQL, Redis, WebSockets/SSE, and MinIO.

## 1. Technical Architecture

### Control Plane (MySQL - Durable State)
- **Purpose**: Stores configuration, business logic, and reporting data
- **Components**:
  - Tenants (multi-tenant isolation)
  - Users and RBAC (roles/permissions)
  - Cloudonix domain configurations
  - Voice agents and agent groups
  - Routing rules (inbound/outbound)
  - Call records and analytics
  - Webhook audit logs

### Execution Plane (Redis + Queues + Real-time)
- **Purpose**: Handles runtime state, distribution logic, and real-time updates
- **Components**:
  - Idempotency keys for webhook processing
  - Distributed locks for race condition prevention
  - Call session state machines
  - Load balancing counters and pointers
  - WebSocket/SSE event broadcasting
  - Job queues for async processing

### Application Layers
```
┌─────────────────┐
│   React SPA     │ ← Admin dashboard, real-time updates
├─────────────────┤
│   Laravel API   │ ← REST endpoints, business logic
├─────────────────┤
│   MySQL/Redis   │ ← Data persistence and ephemeral state
├─────────────────┤
│ Cloudonix APIs  │ ← Voice application webhooks, REST calls
└─────────────────┘
```

## 2. API Definitions

### Authentication & Authorization
- **Bearer Token Auth**: Cloudonix domain-scoped authentication
- **RBAC Middleware**: Tenant-scoped permissions
- **Endpoints**:
  - `POST /api/auth/login`
  - `POST /api/auth/logout`
  - `GET /api/auth/user`

### Voice Agent Management
- **CRUD Operations**:
  - `GET/POST/PUT/DELETE /api/voice-agents`
  - `GET /api/voice-agents/{id}/status`
- **Validation**: Provider whitelist, required fields
- **Supported Providers**: Synthflow, Dasha, Superdash, ElevenLabs, Deepvox, RelayHawk, VoiceHub, Retell variants, VAPI, Fonio, SigmaMind, Modon, PureTalk, Millis variants

### Agent Groups Management
- **CRUD Operations**:
  - `GET/POST/PUT/DELETE /api/agent-groups`
- **Distribution Strategies**:
  - Load Balanced (rolling 24h window)
  - Priority (ordered failover)
  - Round Robin (rotating selection)

### Routing Rules
- **Inbound Rules**:
  - `GET/POST/PUT/DELETE /api/inbound-rules`
  - Pattern matching: prefix-based (e.g., `+123*`)
  - Target: voice agent or agent group
- **Outbound Rules**:
  - `GET/POST/PUT/DELETE /api/outbound-rules`
  - Trunk selection by callerId prefix/country

### Call Records & Analytics
- **Query Endpoints**:
  - `GET /api/call-records` (with filtering)
  - `GET /api/analytics/summary`
  - `GET /api/analytics/live-calls`
- **Export**:
  - `POST /api/call-records/export` (CSV/JSON)

### Voice Application Webhook
- **Endpoint**: `POST /api/webhooks/voice-application`
- **Request Validation**:
  - Cloudonix domain verification
  - Bearer token authentication
  - Idempotency key handling
- **Response**: CXML routing instructions

## 3. Data Models

### MySQL Schema

#### Core Tables
```sql
-- Tenants
CREATE TABLE tenants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    cloudonix_domain VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Voice Agents
CREATE TABLE voice_agents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    provider VARCHAR(100) NOT NULL,
    service_value VARCHAR(255) NOT NULL,
    username VARCHAR(255) NULL,
    password VARCHAR(255) NULL,
    enabled BOOLEAN DEFAULT TRUE,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Agent Groups
CREATE TABLE agent_groups (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    strategy ENUM('load_balanced', 'priority', 'round_robin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Agent Group Members
CREATE TABLE agent_group_members (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    agent_group_id BIGINT NOT NULL,
    voice_agent_id BIGINT NOT NULL,
    priority_order INT NULL, -- For priority strategy
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_group_id) REFERENCES agent_groups(id),
    FOREIGN KEY (voice_agent_id) REFERENCES voice_agents(id),
    UNIQUE KEY unique_group_agent (agent_group_id, voice_agent_id)
);

-- Inbound Routing Rules
CREATE TABLE inbound_routing_rules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    pattern VARCHAR(255) NOT NULL, -- e.g., '+123*'
    target_type ENUM('voice_agent', 'agent_group') NOT NULL,
    target_id BIGINT NOT NULL,
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Outbound Routing Rules
CREATE TABLE outbound_routing_rules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    prefix VARCHAR(50) NOT NULL, -- Country code or number prefix
    trunk_config JSON NOT NULL, -- Cloudonix trunk parameters
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Call Records
CREATE TABLE call_records (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    call_sid VARCHAR(255) NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL,
    from_number VARCHAR(50) NOT NULL,
    to_number VARCHAR(50) NOT NULL,
    status ENUM('queued', 'ringing', 'in-progress', 'completed', 'busy', 'canceled', 'failed', 'no-answer') NOT NULL,
    duration_seconds INT NULL,
    voice_agent_id BIGINT NULL,
    agent_group_id BIGINT NULL,
    routing_rule_id BIGINT NULL,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (voice_agent_id) REFERENCES voice_agents(id),
    FOREIGN KEY (agent_group_id) REFERENCES agent_groups(id),
    FOREIGN KEY (routing_rule_id) REFERENCES inbound_routing_rules(id)
);

-- Webhook Audit
CREATE TABLE webhook_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    call_sid VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY unique_idempotency (tenant_id, idempotency_key)
);
```

### Redis Data Structures

#### Ephemeral State Keys
```
# Idempotency locks
webhook:idempotency:{tenant_id}:{key} → TTL-based lock

# Call session state machines
call:state:{call_sid} → JSON state object

# Load balancing counters (rolling 24h)
agent:calls:{agent_id}:{date} → sorted set of timestamps
agent:total_calls:{agent_id} → counter

# Round robin pointers
group:rr_pointer:{group_id} → current index

# Real-time subscriptions
ws:subscriptions:{tenant_id} → set of connection IDs
```

## 4. Real-time Requirements

### WebSocket/SSE Architecture
- **Backend**: Laravel Broadcasting with Redis driver
- **Frontend**: React hooks for subscription management
- **Events**:
  - `call.started` - New call initiated
  - `call.ended` - Call completed with final status
  - `call.updated` - Status changes during call
  - `agent.status_changed` - Agent enabled/disabled
  - `routing_rule.updated` - Configuration changes

### Event Flow
```
Voice Application Webhook → Process Call → Update Redis State → Broadcast Event → React SPA Update
```

## 5. Security Model

### Authentication
- **Cloudonix Domain Validation**: Verify domain in webhook requests
- **API Key Management**: Secure storage of Cloudonix API keys
- **Bearer Token Auth**: JWT-based session management

### Authorization
- **RBAC**: Role-based permissions (admin, user)
- **Tenant Isolation**: Database queries scoped by tenant_id
- **Middleware Enforcement**: Policy-based access control

### Data Protection
- **Webhook Verification**: Idempotency keys prevent replay attacks
- **Rate Limiting**: Redis-based request throttling
- **Secret Management**: Environment-based credential storage
- **Audit Logging**: All webhook events logged with minimal PII

### Threat Model
- **Cross-tenant Data Leakage**: Mitigated by tenant_id scoping
- **Webhook Spoofing**: Domain and auth validation
- **Race Conditions**: Distributed locks on critical operations
- **DDoS**: Rate limiting and idempotency checks

## 6. Integration Points

### Cloudonix Voice Application
- **Request Handling**: POST webhook with call parameters
- **Response Format**: CXML with `<Dial><Service>` routing
- **Status Callbacks**: Async updates via webhooks
- **Authentication**: Bearer token + domain validation

### Supported Service Providers
Based on Cloudonix documentation, supporting:
- Synthflow (`synthflow`)
- Dasha (`dasha`)
- Superdash (`superdash.ai`)
- ElevenLabs (`11labs`)
- Deepvox (`ultravox`) *
- RelayHawk (`relayhawk`)
- VoiceHub (`voicehub`)
- Retell variants (`retell`, `retell-udp`, `retell-tcp`, `retell-tls`)
- VAPI (`vapi`)
- Fonio (`fonio`)
- SigmaMind (`sigmamind`)
- Modon (`modon`)
- PureTalk (`puretalk`)
- Millis variants (`millis-us`, `millis-eu`)

*Note: Deepvox mapped to `ultravox` based on current docs

### State Machine Implementation
```php
enum CallState: string {
    case INITIATED = 'initiated';
    case ROUTING = 'routing';
    case CONNECTED = 'connected';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
}

class CallStateMachine {
    private Redis $redis;
    private string $callSid;

    public function transition(CallState $from, CallState $to, array $context = []): bool {
        // Validate transition rules
        // Update Redis state
        // Broadcast real-time event
        // Log audit trail
    }
}
```

## 7. Implementation Roadmap

### Phase 1: Core Infrastructure
- [ ] Laravel scaffolding with boilerplate patterns
- [ ] MySQL schema migration setup
- [ ] Redis connection and basic operations
- [ ] Authentication middleware and RBAC
- [ ] Docker Compose configuration

### Phase 2: Voice Agent Management
- [ ] Voice agent CRUD API
- [ ] Agent group management with strategies
- [ ] Provider validation and configuration
- [ ] Frontend admin interface

### Phase 3: Routing Engine
- [ ] Inbound routing rule engine
- [ ] Load balancing algorithms (Redis-backed)
- [ ] CXML response generation
- [ ] Voice application webhook handler

### Phase 4: Real-time Features
- [ ] WebSocket/SSE broadcasting setup
- [ ] React real-time hooks
- [ ] Live call monitoring
- [ ] Dashboard analytics

### Phase 5: Advanced Features
- [ ] Outbound routing rules
- [ ] Call record analytics and export
- [ ] Webhook idempotency and audit
- [ ] ngrok integration for development

### Phase 6: Testing & Deployment
- [ ] Unit tests for state machines
- [ ] Integration tests for webhooks
- [ ] Load testing for distribution logic
- [ ] Production deployment configuration

## 8. Success Metrics

- **Technical Accuracy**: 100% compliance with Cloudonix API specifications
- **Reliability**: <0.1% webhook processing failures
- **Performance**: <100ms routing decision latency
- **Scalability**: Support 1000+ concurrent calls per tenant
- **User Experience**: 95%+ user satisfaction score

## Sources

- [Cloudonix Voice Application Request](https://developers.cloudonix.com/Documentation/voiceApplication/Request)
- [Cloudonix Service Providers](https://developers.cloudonix.com/Documentation/voiceApplication/Verb/dial/serviceProvider)
- [Cloudonix Voice Application Operations](https://developers.cloudonix.com/Documentation/voiceApplication/Operations)