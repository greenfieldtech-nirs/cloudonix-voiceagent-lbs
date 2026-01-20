# Cloudonix Voice Agent Load Balancer

An open-source voice application tool that provides **inbound call load distribution and routing for AI Voice Agents**, plus **outbound call routing controls**, real-time analytics dashboards, and comprehensive call record exports. Built on the Cloudonix Voice SaaS Boilerplate foundation with advanced load balancing strategies, tenant isolation, and extensible voice agent provider support.

This tool enables organizations to efficiently route voice calls to multiple AI agents across different providers using intelligent load balancing algorithms, while providing complete visibility into call performance and agent utilization.

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- Git
- At least 4GB RAM available for containers

### Setup and Run

1. **Clone the repository:**
    ```bash
    git clone <repository-url>
    cd cloudonix-voiceagent-lbs
    ```

2. **Run the setup script:**
    ```bash
    ./setup.sh
    ```

3. **Edit environment configuration:**
    ```bash
    # The setup script creates backend/.env from template
    # Edit backend/.env with your specific configuration
    nano backend/.env
    ```

4. **Start all services:**
    ```bash
    docker-compose up --build
    ```

5. **Run database migrations:**
    ```bash
    docker-compose exec app php artisan migrate
    ```

6. **Access the applications:**
    - **Admin Dashboard**: http://localhost:3000 (register first)
    - **API Endpoints**: http://localhost/api
    - **MinIO Console**: http://localhost:9001 (admin/minioadmin)
    - **Nginx Health Check**: http://localhost/health

### Verification
```bash
# Check if all containers are running
docker-compose ps

# Test API connectivity
curl http://localhost/api/user -H "Authorization: Bearer YOUR_TOKEN"

# Test webhook endpoint (should return 400 for invalid requests)
curl -X POST http://localhost/api/voice/session/cdr \
  -H "Content-Type: application/json" \
  -d '{}'

# Test nginx health
curl http://localhost/health
```

### ngrok Setup for Cloudonix Webhooks

1. **Configure ngrok:**
    ```bash
    ./configure-ngrok.sh
    # Follow the instructions to set up your ngrok auth token
    ```

2. **Start tunnel:**
    ```bash
    ngrok http 80
    # Copy the https URL for webhook configuration
    ```

3. **Configure Cloudonix:**
    - Use the ngrok HTTPS URL as your webhook endpoint
    - Example: `https://abc123.ngrok.io/api/voice/application/your-domain`

## 🏗️ Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 + PHP 8.4 + Laravel Sanctum
- **Frontend**: React 19 + TypeScript + Tailwind CSS + Lucide Icons
- **Database**: MariaDB 10.11 (persistent data)
- **Cache/Queues**: Redis 7 (ephemeral state)
- **Storage**: MinIO (S3-compatible object storage)
- **Web Server**: Nginx (reverse proxy and load balancer)
- **External Access**: ngrok (webhook tunneling)
- **Containerization**: Docker Compose with health checks

### Core Architecture

#### Control Plane (Persistent Data - MySQL)
- **Multi-Tenant System**: Complete tenant isolation with scoped queries
- **Voice Agent Management**: CRUD operations for AI voice agents across 18+ providers
- **Agent Groups**: Load balancing groups with configurable distribution strategies
- **Routing Rules**: Pattern-based inbound routing and trunk-based outbound routing
- **Call Records**: Comprehensive call logging with agent attribution
- **Audit Trail**: Webhook events and system activity logging

#### Execution Plane (Runtime State - Redis)
- **Load Balancing Memory**: Ephemeral counters for real-time agent utilization
- **Session State**: Call lifecycle management with state machine transitions
- **Idempotency Keys**: Webhook deduplication with automatic cleanup
- **Distributed Locks**: Race condition prevention for routing decisions
- **Real-Time Events**: Live dashboard updates and agent status broadcasting

## 🔧 Implemented Features

### ✅ Multi-Tenant Architecture
- **Tenant Isolation**: All database queries are tenant-scoped
- **User Registration**: New users automatically associated with tenants
- **Tenant Management**: Admin interface for tenant configuration
- **Data Security**: Complete separation between tenant data

### ✅ Voice Agent Management
- **Provider Support**: 18+ AI voice providers (VAPI, Synthflow, Dasha, Eleven Labs, etc.)
- **Agent Configuration**: Service value, authentication credentials, metadata
- **Real-Time Status**: Enable/disable agents with immediate routing impact
- **Provider Validation**: Type-specific validation for each voice agent provider

### ✅ Agent Group Load Balancing
- **Distribution Strategies**: Load Balanced, Priority, and Round Robin algorithms
- **Redis Memory**: Ephemeral counters for real-time load distribution
- **Group Membership**: Drag-and-drop agent assignment with capacity weights
- **Strategy Configuration**: Rolling windows, fallback behavior, priority ordering

### ✅ Intelligent Call Routing
- **Inbound Routing**: Pattern-based routing to agents or groups
- **Outbound Routing**: Trunk-based routing with caller ID detection
- **Fallback Logic**: Automatic fallback to alternative agents/groups
- **Rule Management**: Priority-based rule evaluation and conflict resolution

### ✅ Real-Time Analytics Dashboard
- **Live Metrics**: Calls/day, success rates, active calls with real-time updates
- **Agent Performance**: Utilization tracking and performance analytics
- **Group Statistics**: Load distribution and efficiency metrics
- **WebSocket Updates**: Live dashboard without polling

### ✅ Call Record Management
- **Comprehensive Logging**: All call events with agent and group attribution
- **Advanced Filtering**: By agent, group, date range, status, duration
- **Audit Trail**: Webhook processing history and system events
- **Export Functionality**: CSV/JSON export with field selection

### ✅ Cloudonix Integration
- **CXML Generation**: Cloudonix-compliant XML for all routing scenarios
- **Webhook Processing**: Idempotent handling of voice application requests
- **State Machine**: Call lifecycle management with 9 distinct states
- **Provider Templates**: Pre-built CXML templates for all supported providers

### ✅ Authentication & Authorization
- **Laravel Sanctum**: Token-based API authentication
- **Registration/Login**: Complete user lifecycle management
- **Profile Management**: User settings and password changes
- **Protected Routes**: JWT token validation on all admin endpoints

## 📡 API Documentation

### Voice Agent Management Endpoints

#### List Voice Agents
```http
GET /api/voice-agents?page=1&per_page=20&search=support&enabled=true&provider=vapi
Authorization: Bearer YOUR_TOKEN
```

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20, max: 100)
- `search`: Search in agent names
- `enabled`: Filter by enabled status
- `provider`: Filter by provider type

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "name": "Customer Support Agent",
      "provider": "vapi",
      "service_value": "agent_12345",
      "enabled": true,
      "metadata": {
        "region": "us-east",
        "language": "en-US"
      },
      "created_at": "2024-01-01T00:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

#### Create Voice Agent
```http
POST /api/voice-agents
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Sales Agent",
  "provider": "synthflow",
  "service_value": "https://api.synthflow.com/agent/123",
  "username": "api_key_123",
  "password": "secret_key_456",
  "enabled": true,
  "metadata": {
    "department": "sales",
    "priority": "high"
  }
}
```

#### Update Voice Agent
```http
PUT /api/voice-agents/1
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Updated Sales Agent",
  "enabled": false
}
```

#### Toggle Agent Status
```http
PATCH /api/voice-agents/1/toggle
Authorization: Bearer YOUR_TOKEN
```

### Agent Group Management Endpoints

#### List Agent Groups
```http
GET /api/agent-groups?page=1&per_page=20&strategy=load_balanced
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "tenant_id": 1,
      "name": "Support Team",
      "strategy": "load_balanced",
      "settings": {
        "window_hours": 24,
        "fallback_enabled": true
      },
      "agents": [
        {
          "id": 1,
          "name": "Agent 1",
          "provider": "vapi",
          "pivot": {
            "priority": 1,
            "capacity": 2
          }
        }
      ],
      "created_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

#### Create Agent Group
```http
POST /api/agent-groups
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "name": "Emergency Support",
  "strategy": "priority",
  "settings": {
    "fallback_enabled": true
  }
}
```

#### Add Agent to Group
```http
POST /api/agent-groups/1/members
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "agent_id": 2,
  "priority": 2,
  "capacity": 1
}
```

### Routing Rules Endpoints

#### Get Inbound Routing Rules
```http
GET /api/inbound-routing-rules
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
[
  {
    "id": 1,
    "tenant_id": 1,
    "pattern": "+1234567890",
    "target_type": "group",
    "target_id": 1,
    "priority": 1,
    "enabled": true,
    "created_at": "2024-01-01T00:00:00Z"
  }
]
```

#### Create Inbound Routing Rule
```http
POST /api/inbound-routing-rules
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "pattern": "+1555*",
  "target_type": "agent",
  "target_id": 2,
  "priority": 2,
  "enabled": true
}
```

### Analytics Endpoints

#### Get Analytics Metrics
```http
GET /api/analytics/metrics?start_date=2024-01-01&end_date=2024-01-31&agent_id=1
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "calls_today": 150,
  "success_rate": 0.95,
  "avg_duration": 180,
  "active_calls": 3,
  "trends": [
    {
      "date": "2024-01-01",
      "calls": 120,
      "success_rate": 0.94
    }
  ]
}
```

#### Get Call Records
```http
GET /api/analytics/call-records?page=1&per_page=50&agent_id=1&start_date=2024-01-01
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "session_token": "session_123",
      "direction": "inbound",
      "from_number": "+1234567890",
      "to_number": "+0987654321",
      "agent_id": 1,
      "group_id": null,
      "status": "completed",
      "start_time": "2024-01-01T10:00:00Z",
      "end_time": "2024-01-01T10:02:30Z",
      "duration": 150,
      "created_at": "2024-01-01T10:02:35Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1000
  }
}
```

### Authentication Endpoints

#### Register User
```http
POST /api/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

Response:
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "tenant_id": 1
  },
  "token": "1|abc123..."
}
```

#### Get Current User
```http
GET /api/user
Authorization: Bearer YOUR_TOKEN
```

#### Logout
```http
POST /api/logout
Authorization: Bearer YOUR_TOKEN
```

### Call Monitoring Endpoints

#### Get Active Calls
```http
GET /api/calls/active
Authorization: Bearer YOUR_TOKEN
```

Response:
```json
{
  "data": [
    {
      "id": 1,
      "session_id": "sess_123",
      "token": "call_token_abc",
      "caller_id": "+1234567890",
      "destination": "+0987654321",
      "status": "ringing",
      "call_start_time": "2024-01-18T10:00:00Z",
      "duration_seconds": 15
    }
  ]
}
```

#### Get Call Statistics
```http
GET /api/calls/statistics
Authorization: Bearer YOUR_TOKEN
```

Response:
```json
{
  "active_calls": 5,
  "completed_today": 142,
  "total_today": 147
}
```

### CDR (Call Detail Records) Endpoints

#### Get CDR Records with Filtering
```http
GET /api/cdr?page=1&per_page=50&disposition=ANSWER&start_date=2024-01-01&end_date=2024-01-18
Authorization: Bearer YOUR_TOKEN
```

Query Parameters:
- `page`: Page number (default: 1)
- `per_page`: Records per page (default: 50, max: 200)
- `from`: Filter by caller number (partial match)
- `to`: Filter by destination number (partial match)
- `disposition`: Filter by call disposition (ANSWER, BUSY, CANCEL, FAILED, CONGESTION, NOANSWER)
- `token`: Filter by session token (partial match)
- `start_date`: Start date (YYYY-MM-DD)
- `end_date`: End date (YYYY-MM-DD)

Response:
```json
{
  "data": [
    {
      "id": 1,
      "call_id": "call_123456",
      "session_token": "session_abc123",
      "from_number": "+1234567890",
      "to_number": "+0987654321",
      "direction": "inbound",
      "disposition": "ANSWER",
      "start_time": "2024-01-18T10:00:00Z",
      "answer_time": "2024-01-18T10:00:05Z",
      "end_time": "2024-01-18T10:02:15Z",
      "duration_seconds": 135,
      "billsec": 130,
      "domain": "tenant.cloudonix.com",
      "created_at": "2024-01-18T10:02:20Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1250,
    "last_page": 25,
    "from": 1,
    "to": 50
  },
  "filters_applied": {
    "disposition": "ANSWER",
    "start_date": "2024-01-01",
    "end_date": "2024-01-18"
  }
}
```

#### Get Single CDR Record
```http
GET /api/cdr/123
Authorization: Bearer YOUR_TOKEN
```

### Webhook Endpoints

#### Voice Application Webhook
```http
POST /api/voice/application/{domain}
Content-Type: application/x-www-form-urlencoded
X-CX-APIKey: api_key_here
X-CX-Domain: tenant.cloudonix.com

CallSid=CA1234567890&From=%2B1234567890&To=%2B0987654321&Direction=inbound&Session=session_123
```

**Response:** CXML routing instructions
```xml
<?xml version="1.0" encoding="UTF-8"?>
<Response>
  <Dial callerId="+1234567890" action="/api/voice/callback" method="POST">
    <Service provider="vapi">agent_12345</Service>
  </Dial>
</Response>
```

#### Session Update Webhook
```http
POST /api/voice/session/update/{domain}
Content-Type: application/json

{
  "CallSid": "CA1234567890",
  "Session": "session_123",
  "CallStatus": "completed",
  "Duration": 150
}
```

#### CDR Webhook
```http
POST /api/voice/session/cdr/{domain}
Content-Type: application/json

{
  "call_id": "CA1234567890",
  "session_token": "session_123",
  "from": "+1234567890",
  "to": "+0987654321",
  "disposition": "ANSWER",
  "duration": 150,
  "start_time": "2024-01-01T10:00:00Z"
}
```

#### Session Update Webhook
```http
POST /api/voice/session/update
Content-Type: application/json

{
  "id": 12345,
  "domain": "tenant.cloudonix.com",
  "token": "session_token_abc123",
  "status": "answered",
  "callerId": "+1234567890",
  "destination": "+0987654321",
  "direction": "inbound",
  "createdAt": "2024-01-18T10:00:00Z",
  "modifiedAt": "2024-01-18T10:00:05Z",
  "callStartTime": 1705572000000,
  "answerTime": "2024-01-18T10:00:05Z",
  "vappServer": "vapp-01"
}
```

#### CDR Callback Webhook
```http
POST /api/voice/session/cdr
Content-Type: application/json

{
  "call_id": "call_123456",
  "domain": "tenant.cloudonix.com",
  "from": "+1234567890",
  "to": "+0987654321",
  "disposition": "ANSWERED",
  "duration": 135,
  "billsec": 130,
  "timestamp": 1705572140,
  "session": {
    "token": "session_token_abc123",
    "callStartTime": 1705572000000,
    "callAnswerTime": 1705572005000,
    "callEndTime": 1705572135000,
    "vappServer": "vapp-01"
  }
}
```

## 📁 Project Structure

```
cloudonix-voiceagent-lbs/
├── LICENSE                     # MIT License
├── README.md                   # This file
├── CHANGELOG.md                # Version history and changes
├── AGENTS.md                   # Development system documentation
├── docker-compose.yml          # Service orchestration
├── planning/                   # Project planning documents
│   ├── 01_Product_Requirements_Document.md
│   ├── 02_Product_Specification.md
│   ├── 03_Implementation_Plans.md
│   └── 04_Implementation_Tracker.md
├── docker/
│   ├── app/                    # Laravel container (PHP 8.4)
│   │   ├── Dockerfile
│   │   └── supervisord.conf
│   ├── web/                    # React container (Node.js)
│   │   ├── Dockerfile
│   │   └── nginx.conf
│   └── db/                     # Database initialization
│       └── init.sql
├── backend/                    # Laravel 12 application
│   ├── app/
│   │   ├── Models/            # Eloquent models (15+ models)
│   │   │   ├── Tenant.php
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   ├── Permission.php
│   │   │   ├── PhoneNumber.php
│   │   │   ├── RoutingRule.php
│   │   │   ├── VoiceApplication.php
│   │   │   ├── Integration.php
│   │   │   ├── CallSession.php
│   │   │   ├── CallEvent.php
│   │   │   ├── CdrLog.php
│   │   │   ├── VoiceAgent.php     # NEW: Voice agent management
│   │   │   ├── AgentGroup.php     # NEW: Load balancing groups
│   │   │   ├── InboundRoutingRule.php  # NEW: Routing rules
│   │   │   ├── OutboundRoutingRule.php # NEW: Outbound routing
│   │   │   ├── CallRecord.php     # NEW: Enhanced call logging
│   │   │   └── WebhookAudit.php   # NEW: Event audit trail
│   │   ├── Services/            # NEW: Business logic services
│   │   │   ├── RedisKeyPatterns.php
│   │   │   ├── RedisService.php
│   │   │   ├── CallStateMachine.php
│   │   │   ├── IdempotencyService.php
│   │   │   └── CxmlService.php
│   │   └── Api/Contracts/       # NEW: API specifications
│   │       └── ApiContracts.php
│   ├── database/migrations/    # Database schema (20+ migrations)
│   ├── routes/
│   │   └── api.php            # API route definitions
│   ├── tests/                 # PHPUnit tests
│   │   ├── Feature/
│   │   │   ├── AuthControllerTest.php
│   │   │   ├── CallStateMachineTest.php    # NEW: State machine tests
│   │   │   ├── IdempotencyServiceTest.php  # NEW: Idempotency tests
│   │   │   ├── CxmlServiceTest.php         # NEW: CXML generation tests
│   │   │   └── VoiceApplicationControllerTest.php
│   │   └── Unit/
│   ├── REDIS_ARCHITECTURE.md  # NEW: Redis usage documentation
│   ├── api-documentation.json # NEW: OpenAPI specification
│   └── composer.json          # PHP dependencies
├── frontend/                   # React 19 application
│   ├── src/
│   │   ├── components/        # Reusable React components
│   │   │   ├── AdminLayout.tsx
│   │   │   │   ├── toast/        # Notification system
│   │   │   │   └── ...
│   │   ├── pages/            # Page components
│   │   │   ├── LandingPage.tsx
│   │   │   ├── Login.tsx
│   │   │   ├── Register.tsx
│   │   │   ├── Dashboard.tsx
│   │   │   ├── LiveCalls.tsx
│   │   │   ├── CallLogs.tsx
│   │   │   └── ...
│   │   ├── App.tsx           # Main application component
│   │   └── index.tsx         # Application entry point
│   └── package.json          # Node.js dependencies
└── .gitignore                # Git ignore rules
```
cloudonix-voiceagent-lbs/
├── LICENSE                     # MIT License
├── README.md                   # This file
├── docker-compose.yml          # Service orchestration
├── AGENTS.md                   # Development system documentation
├── docker/
│   ├── app/                    # Laravel container (PHP 8.4)
│   │   ├── Dockerfile
│   │   └── supervisord.conf
│   ├── web/                    # React container (Node.js)
│   │   ├── Dockerfile
│   │   └── nginx.conf
│   ├── nginx/                  # Nginx reverse proxy
│   │   ├── Dockerfile
│   │   └── default.conf
│   └── db/                     # Database initialization
│       └── init.sql
├── backend/                    # Laravel 12 application
│   ├── app/
│   │   ├── Models/            # Eloquent models (11 models)
│   │   │   ├── Tenant.php
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   ├── Permission.php
│   │   │   ├── PhoneNumber.php
│   │   │   ├── RoutingRule.php
│   │   │   ├── VoiceApplication.php
│   │   │   ├── Integration.php
│   │   │   ├── CallSession.php
│   │   │   ├── CallEvent.php
│   │   │   └── CdrLog.php
│   │   └── Http/Controllers/Api/  # API controllers
│   │       ├── AuthController.php
│   │       ├── TenantController.php
│   │       ├── CallController.php
│   │       ├── CdrController.php
│   │       └── VoiceApplicationController.php
│   ├── database/migrations/    # Database schema (15+ migrations)
│   ├── routes/
│   │   └── api.php            # API route definitions
│   ├── tests/                 # PHPUnit tests
│   │   ├── Feature/
│   │   │   ├── AuthControllerTest.php
│   │   │   └── VoiceApplicationControllerTest.php
│   │   └── Unit/
│   └── composer.json          # PHP dependencies
├── frontend/                   # React 19 application
│   ├── src/
│   │   ├── components/        # Reusable React components
│   │   │   ├── AdminLayout.tsx
│   │   │   ├── toast/        # Notification system
│   │   │   └── ...
│   │   ├── pages/            # Page components
│   │   │   ├── LandingPage.tsx
│   │   │   ├── Login.tsx
│   │   │   ├── Register.tsx
│   │   │   ├── Dashboard.tsx
│   │   │   ├── LiveCalls.tsx
│   │   │   ├── CallLogs.tsx
│   │   │   └── ...
│   │   ├── App.tsx           # Main application component
│   │   └── index.tsx         # Application entry point
│   └── package.json          # Node.js dependencies
└── .gitignore                # Git ignore rules
```

## 🛠️ Development Workflow

### Backend Development (Laravel)

```bash
# Access Laravel container
docker-compose exec app bash

# Run migrations
php artisan migrate

# Run migrations with seeding
php artisan migrate:fresh --seed

# Run tests
php artisan test

# Run specific test
php artisan test tests/Feature/VoiceApplicationControllerTest.php

# Access Tinker (Laravel REPL)
php artisan tinker

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Check code style
./vendor/bin/pint --test
```

### Frontend Development (React)

```bash
# Access React container
docker-compose exec web bash

# Install dependencies
npm install

# Start development server (with hot reload)
npm start

# Run tests
npm test

# Build for production
npm run build

# Type checking
npx tsc --noEmit
```

### Database Management

```bash
# Access MariaDB container
docker-compose exec db bash

# Connect to database
mysql -u root -p cloudonix_boilerplate

# View database structure
SHOW TABLES;
DESCRIBE tenants;
DESCRIBE users;

# Backup database
mysqldump -u root -p cloudonix_boilerplate > backup.sql
```

### Docker Workflow

```bash
# View running containers
docker-compose ps

# View logs
docker-compose logs app
docker-compose logs web
docker-compose logs db

# Restart specific service
docker-compose restart app

# Rebuild and restart all services
docker-compose up --build --force-recreate

# Clean up
docker-compose down -v  # Remove volumes too
docker system prune -a  # Clean up unused images
```

## 🧪 Testing

### Backend Testing (PHPUnit)
```bash
# Run all tests
docker-compose exec app php artisan test

# Run with coverage
docker-compose exec app php artisan test --coverage

# Run specific test class
docker-compose exec app php artisan test tests/Feature/AuthControllerTest.php

# Run tests in group
docker-compose exec app php artisan test --testsuite=Feature
```

### Frontend Testing (Jest + React Testing Library)
```bash
# Run all tests
docker-compose exec web npm test

# Run tests in watch mode
docker-compose exec web npm test -- --watch

# Run with coverage
docker-compose exec web npm test -- --coverage

# Run specific test
docker-compose exec web npm test -- Login.test.tsx
```

### Integration Testing
```bash
# Test webhook endpoints
curl -X POST http://localhost:8000/api/voice/session/cdr \
  -H "Content-Type: application/json" \
  -d @test-webhook-payload.json

# Test API authentication
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test protected endpoints
curl http://localhost:8000/api/cdr \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🔒 Security Features

- **Tenant Isolation**: All database queries automatically scoped to authenticated user's tenant
- **API Authentication**: Laravel Sanctum token-based authentication with automatic token refresh
- **Input Validation**: Comprehensive validation on all API endpoints using Laravel's validation rules
- **Webhook Security**: Basic validation of incoming Cloudonix webhooks (headers, structure)
- **RBAC Ready**: Complete roles and permissions system implemented (ready for UI)
- **Environment Security**: Sensitive configuration stored in environment variables
- **SQL Injection Protection**: Eloquent ORM with parameterized queries
- **XSS Protection**: React's automatic escaping and CSP headers

## 📊 Database Schema

### Core Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `tenants` | Multi-tenant isolation | `id`, `name`, `domain`, `settings`, `trial_ends_at` |
| `users` | User accounts | `id`, `tenant_id`, `name`, `email`, `password` |
| `roles` | RBAC roles | `id`, `name`, `guard_name` |
| `permissions` | RBAC permissions | `id`, `name`, `guard_name` |
| `role_user` | User-role assignments | `user_id`, `role_id` |
| `permission_role` | Role-permission assignments | `permission_id`, `role_id` |
| `voice_agents` | AI voice agent configurations | `id`, `tenant_id`, `name`, `provider`, `service_value`, `enabled` |
| `agent_groups` | Load balancing groups | `id`, `tenant_id`, `name`, `strategy`, `settings` |
| `agent_group_memberships` | Group-agent relationships | `group_id`, `agent_id`, `priority`, `capacity` |
| `inbound_routing_rules` | Call routing rules | `id`, `tenant_id`, `pattern`, `target_type`, `target_id`, `priority` |
| `outbound_routing_rules` | Outbound routing rules | `id`, `tenant_id`, `caller_id`, `destination_pattern`, `trunk_config` |
| `call_records` | Enhanced call logging | `id`, `tenant_id`, `session_token`, `agent_id`, `group_id`, `status`, `duration` |
| `webhook_audit` | Event processing audit | `id`, `tenant_id`, `event_type`, `session_token`, `payload`, `processed_at` |
| `integrations` | Cloudonix API credentials | `id`, `tenant_id`, `provider`, `credentials` |
| `phone_numbers` | Phone number management | `id`, `tenant_id`, `number`, `capabilities` |
| `routing_rules` | Legacy routing logic | `id`, `tenant_id`, `pattern`, `action` |
| `voice_applications` | CXML application definitions | `id`, `tenant_id`, `provider_app_id`, `cxml_definition` |
| `call_sessions` | Runtime call state | `id`, `tenant_id`, `session_id`, `token`, `status` |
| `call_events` | Webhook event audit | `id`, `tenant_id`, `call_session_id`, `event_type`, `payload` |
| `cdr_logs` | Call Detail Records | `id`, `tenant_id`, `call_id`, `disposition`, `duration_seconds` |

### Relationships
- **Tenant → Users**: One-to-many
- **Tenant → All other entities**: One-to-many (scoped queries)
- **Users → Roles**: Many-to-many via `role_user`
- **Roles → Permissions**: Many-to-many via `permission_role`
- **Voice Agents → Agent Groups**: Many-to-many via `agent_group_memberships`
- **Agent Groups → Voice Agents**: Many-to-many via `agent_group_memberships`
- **Inbound Rules → Voice Agents/Groups**: Polymorphic via `target_type`/`target_id`
- **Call Records → Voice Agents/Groups**: Optional foreign keys
- **Webhook Audit → Call Records**: Reference via `session_token`
- **Call Sessions → Call Events**: One-to-many
- **CDR Logs → Call Sessions**: Reference via `session_token`

## 🚀 Deployment

### Docker-based Deployment

1. **Environment Configuration:**
   ```bash
   cp backend/.env.example backend/.env
   # Edit .env with production values
   ```

2. **Build for Production:**
   ```bash
   docker-compose -f docker-compose.prod.yml up --build
   ```

3. **SSL/TLS Setup:**
   - Configure reverse proxy (nginx/caddy) with SSL certificates
   - Update `APP_URL` in environment variables

4. **Database Migration:**
   ```bash
   docker-compose exec app php artisan migrate --force
   ```

### Production Considerations

- **Environment Variables**: Set strong secrets for database, Redis, and MinIO
- **SSL Termination**: Configure HTTPS on reverse proxy
- **Database Backup**: Set up automated backups for MariaDB
- **Monitoring**: Configure health checks and logging
- **Scaling**: Consider Redis clustering for high availability

## 🤝 Contributing

We welcome contributions to improve the Cloudonix Voice Service SaaS Boilerplate!

### Development Setup
1. Follow the Quick Start guide above
2. Create a new branch for your feature: `git checkout -b feature/your-feature-name`
3. Make your changes following the existing code style
4. Add tests for new functionality
5. Ensure all tests pass: `docker-compose exec app php artisan test`
6. Submit a pull request

### Code Style
- **Backend**: Follow Laravel conventions and PSR-12 standards
- **Frontend**: Follow React/TypeScript best practices
- **Commits**: Use clear, descriptive commit messages
- **Testing**: Add tests for new features and bug fixes

### Areas for Contribution
- **Voice Agent Providers**: Support for additional AI voice providers (Google Dialogflow, IBM Watson, etc.)
- **Load Balancing Algorithms**: Advanced distribution strategies (geographic routing, skill-based routing)
- **Real-Time Dashboard**: Enhanced analytics with custom metrics and visualizations
- **Admin UI**: Complete voice agent management interface with drag-and-drop configuration
- **Integration APIs**: Third-party CRM and helpdesk system integrations
- **Performance Optimization**: High-volume call processing and Redis clustering
- **Monitoring**: Advanced alerting and predictive analytics for agent utilization
- **Documentation**: Voice agent setup guides and troubleshooting manuals

### Pull Request Process
1. Update the README.md if you add new features
2. Ensure your code follows the existing patterns
3. Add appropriate tests
4. Update any relevant documentation
5. Request review from maintainers

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- **Laravel**: The PHP framework for web artisans
- **React**: A JavaScript library for building user interfaces
- **Cloudonix**: Voice communication platform
- **Docker**: Containerization platform
- **MySQL**: Reliable SQL database for persistent data
- **Redis**: In-memory data structure store for real-time operations
- **MinIO**: S3-compatible object storage
- **AI Voice Providers**: VAPI, Synthflow, Dasha, Eleven Labs, and all supported providers
- **Open Source Community**: For the tools and libraries that make this possible

---

Built with ❤️ for organizations leveraging AI voice agents in their communication workflows</content>
<parameter name="filePath">README.md