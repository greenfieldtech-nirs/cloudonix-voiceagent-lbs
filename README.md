# Cloudonix Voice Service SaaS Boilerplate

A comprehensive boilerplate for building multi-tenant voice services integrated with Cloudonix, featuring complete authentication, real-time call monitoring, historical analytics, and webhook processing. Built with Laravel 12, React, and modern containerization.

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- Git
- At least 4GB RAM available for containers

### Setup and Run

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd cloudonix-boilerplate
   ```

2. **Start all services:**
   ```bash
   docker-compose up --build
   ```

3. **Run database migrations:**
   ```bash
   docker-compose exec app php artisan migrate
   ```

4. **Access the applications:**
   - **Landing Page**: http://localhost:3000
   - **Admin Dashboard**: http://localhost:3000/admin (register first)
   - **Laravel API**: http://localhost:8000
   - **MinIO Console**: http://localhost:9001 (admin/minioadmin)

### Verification
```bash
# Check if all containers are running
docker-compose ps

# Test API connectivity
curl http://localhost:8000/api/user -H "Authorization: Bearer YOUR_TOKEN"

# Test webhook endpoint (should return 400 for invalid requests)
curl -X POST http://localhost:8000/api/voice/session/cdr \
  -H "Content-Type: application/json" \
  -d '{}'
```

## 🏗️ Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 + PHP 8.4 + Laravel Sanctum
- **Frontend**: React 19 + TypeScript + Tailwind CSS + Lucide Icons
- **Database**: MariaDB 10.11 (persistent data)
- **Cache/Queues**: Redis 7 (ephemeral state)
- **Storage**: MinIO (S3-compatible object storage)
- **Containerization**: Docker Compose with health checks

### Core Architecture

#### Control Plane (Persistent Data - MariaDB)
- **Multi-Tenant System**: Complete tenant isolation with scoped queries
- **User Management**: Authentication, profiles, and settings
- **RBAC**: Roles and permissions system
- **Configuration**: Phone numbers, routing rules, voice applications
- **Audit Trail**: Call events and historical data

#### Execution Plane (Runtime State - Redis)
- **Call Sessions**: Real-time call state management
- **Webhook Processing**: Event-driven updates with idempotency
- **Live Monitoring**: Active call tracking and statistics

## 🔧 Implemented Features

### ✅ Multi-Tenant Architecture
- **Tenant Isolation**: All database queries are tenant-scoped
- **User Registration**: New users automatically associated with tenants
- **Tenant Management**: Admin interface for tenant configuration
- **Data Security**: Complete separation between tenant data

### ✅ Authentication & Authorization
- **Laravel Sanctum**: Token-based API authentication
- **Registration/Login**: Complete user lifecycle management
- **Profile Management**: User settings and password changes
- **Protected Routes**: JWT token validation on all admin endpoints

### ✅ Cloudonix Integration
- **Voice Application Webhooks**: Handle incoming call requests
- **Session Update Webhooks**: Real-time call status updates
- **CDR Processing**: Call Detail Record ingestion and storage
- **Webhook Validation**: Basic security checks on incoming webhooks

### ✅ Real-Time Call Monitoring
- **Live Calls Page**: Active call display with auto-refresh
- **Call Statistics**: Active calls, completion rates, totals
- **Status Indicators**: Visual call state representation
- **Manual Refresh**: User-controlled data updates

### ✅ Historical Call Analytics
- **Call Logs Page**: Complete CDR data with advanced filtering
- **Statistics Dashboard**: 6 key metrics in real-time
- **Advanced Filtering**: By phone numbers, disposition, date ranges, tokens
- **Pagination**: Efficient handling of large datasets
- **Export Ready**: CSV export functionality prepared

### ✅ Admin Interface
- **Dashboard**: Overview with key metrics
- **User Management**: Profile and settings management
- **Call Monitoring**: Live calls and historical logs
- **Responsive Design**: Mobile-friendly interface
- **Toast Notifications**: User feedback system

## 📡 API Documentation

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

#### Voice Application Request
```http
POST /api/voice/application/{applicationId}
Content-Type: application/xml
X-Cloudonix-Signature: signature_here

<?xml version="1.0" encoding="UTF-8"?>
<Request>
  <CallSid>CA1234567890</CallSid>
  <From>+1234567890</From>
  <To>+0987654321</To>
  <!-- Additional Cloudonix parameters -->
</Request>
```

Response: CXML instructions for call handling

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
cloudonix-boilerplate/
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
| `integrations` | Cloudonix API credentials | `id`, `tenant_id`, `provider`, `credentials` |
| `phone_numbers` | Phone number management | `id`, `tenant_id`, `number`, `capabilities` |
| `routing_rules` | Call routing logic | `id`, `tenant_id`, `pattern`, `action` |
| `voice_applications` | CXML application definitions | `id`, `tenant_id`, `provider_app_id`, `cxml_definition` |
| `call_sessions` | Runtime call state | `id`, `tenant_id`, `session_id`, `token`, `status` |
| `call_events` | Webhook event audit | `id`, `tenant_id`, `call_session_id`, `event_type`, `payload` |
| `cdr_logs` | Call Detail Records | `id`, `tenant_id`, `call_id`, `disposition`, `duration_seconds` |

### Relationships
- **Tenant → Users**: One-to-many
- **Tenant → All other entities**: One-to-many (scoped queries)
- **Users → Roles**: Many-to-many via `role_user`
- **Roles → Permissions**: Many-to-many via `permission_role`
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
- **Cloudonix API Integration**: Enhanced API client with full Cloudonix API coverage
- **Real-Time Features**: WebSocket/SSE implementation for live updates
- **Admin UI**: Complete tenant management and RBAC interfaces
- **Export Features**: CSV/PDF export for call logs and reports
- **Monitoring**: Application performance monitoring and alerting
- **Documentation**: API documentation, deployment guides, tutorials

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
- **MariaDB**: Reliable SQL database
- **Redis**: In-memory data structure store
- **MinIO**: S3-compatible object storage

---

Built with ❤️ for the voice communication community</content>
<parameter name="filePath">README.md