# Cloudonix Voice Agent Load Balancer

A production-ready, multi-tenant voice routing platform that intelligently distributes inbound and outbound calls to voice AI agents based on configurable rules and load balancing strategies.

## 🚀 Features

### Core Functionality
- **Intelligent Call Routing**: Rule-based routing with pattern matching and priority handling
- **Agent Group Management**: Load balancing strategies (Round Robin, Priority, Load Balanced)
- **Real-Time Analytics**: Live dashboards with call metrics and performance tracking
- **Advanced Filtering**: Multi-criteria search across all call data with pagination
- **Data Export**: Background CSV/JSON export with field selection and email notifications
- **Audit Trail**: Complete webhook processing logs with security monitoring

### Security & Compliance
- **Multi-Tenant Isolation**: Complete data separation with tenant-scoped access control
- **Webhook Security**: IP validation, rate limiting, replay attack prevention, signature verification
- **Data Protection**: Encryption at rest, GDPR-compliant data handling, PII anonymization
- **Access Control**: Role-based permissions with granular resource authorization
- **Threat Model**: Comprehensive STRIDE analysis with implemented security controls

### Production Ready
- **Docker Deployment**: Production-optimized containers with security hardening
- **Real-Time Updates**: WebSocket broadcasting for live dashboard updates
- **Scalable Architecture**: Redis pub/sub for horizontal scaling and high-throughput
- **Monitoring**: Health checks, performance metrics, and automated alerting
- **Testing Tools**: Webhook simulator and comprehensive integration test suite

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Cloudonix     │────│   Load Balancer │────│   Voice Agents  │
│   Webhooks      │    │                 │    │                 │
└─────────────────┘    │ ┌─────────────┐ │    └─────────────────┘
                       │ │ Rules Engine│ │
┌─────────────────┐    │ └─────────────┘ │    ┌─────────────────┐
│   Admin UI      │────│ ┌─────────────┐ │────│   Analytics     │
│   (React)       │    │ │Load Balancer│ │    │   Dashboard     │
└─────────────────┘    │ └─────────────┘ │    └─────────────────┘
                       │ ┌─────────────┐ │
                       │ │   Analytics │ │
                       │ └─────────────┘ │
                       └─────────────────┘
```

## 📋 Prerequisites

- **Docker & Docker Compose** (v20.10+)
- **Git** (v2.30+)
- **ngrok** (optional, for webhook testing)
- **4GB RAM** minimum, 8GB recommended
- **Linux/Mac/Windows** with Docker support

## 🚀 Quick Start

### Development Environment

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd cloudonix-voiceagent-lbs
   ```

2. **Start the development environment**
   ```bash
   # One-command startup (recommended)
   ./scripts/start.sh

   # Or manually with Docker Compose
   docker-compose up --build -d
   ```

3. **Access the application**
   - **Web Application**: http://localhost
   - **Laravel API**: http://localhost:8000
   - **React Dev Server**: http://localhost:3000
   - **Database**: localhost:3306 (root/password)
   - **Redis**: localhost:6379
   - **MinIO**: http://localhost:9000 (minioadmin/minioadmin)

### Production Deployment

1. **Configure environment variables**
   ```bash
   cp .env.example .env
   # Edit .env with production values
   ```

2. **Deploy with Docker Compose**
   ```bash
   docker-compose -f docker-compose.prod.yml up --build -d
   ```

3. **Run initial setup**
   ```bash
   docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
   docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
   ```

## 🔧 Configuration

### Environment Variables

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `APP_KEY` | Laravel application key | - | Yes |
| `DB_CONNECTION` | Database connection | mysql | Yes |
| `DB_HOST` | Database host | db | Yes |
| `DB_DATABASE` | Database name | cloudonix_voiceagent_lbs | Yes |
| `DB_USERNAME` | Database username | root | Yes |
| `DB_PASSWORD` | Database password | - | Yes |
| `REDIS_PASSWORD` | Redis password | - | No |
| `BROADCAST_DRIVER` | Broadcasting driver | redis | No |
| `MINIO_ACCESS_KEY` | MinIO access key | - | Yes |
| `MINIO_SECRET_KEY` | MinIO secret key | - | Yes |

### ngrok Integration (Optional)

For webhook testing with external services:

1. **Install ngrok**
   ```bash
   # Download from https://ngrok.com/download
   # Or using package manager
   ```

2. **Configure authtoken**
   ```bash
   ngrok authtoken YOUR_AUTH_TOKEN
   ```

3. **Start with ngrok**
   ```bash
   ./scripts/start.sh --profiles ngrok
   ```

4. **Test webhooks**
   ```bash
   php scripts/webhook-simulator.php flow inbound
   ```

## 🧪 Testing

### Webhook Simulation

Use the built-in webhook simulator to test Cloudonix integration:

```bash
# Send a single webhook event
php scripts/webhook-simulator.php send voice.application.request

# Simulate a complete call flow
php scripts/webhook-simulator.php flow inbound

# Load testing
php scripts/webhook-simulator.php load voice.application.request 100

# List available event types
php scripts/webhook-simulator.php list
```

### Running Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test suite
docker-compose exec app php artisan test --filter WebhookSecurityServiceTest

# Run with coverage
docker-compose exec app php artisan test --coverage
```

## 📊 API Documentation

### Authentication
All API endpoints require authentication using Laravel Sanctum tokens.

### Key Endpoints

#### Voice Applications
- `POST /api/voice/application/{applicationId}` - Process voice application webhooks

#### Analytics
- `GET /api/analytics/overview` - Get dashboard metrics
- `GET /api/analytics/trends` - Get call trends data
- `GET /api/analytics/agents` - Get agent performance data

#### Call Records
- `GET /api/call-records` - List call records with filtering
- `GET /api/call-records/{id}` - Get specific call record
- `GET /api/call-records/statistics/summary` - Get statistics

#### Export
- `POST /api/exports` - Queue data export
- `GET /api/exports/{id}/status` - Check export status
- `GET /api/exports/{id}/download` - Download completed export

## 🔒 Security

### Webhook Security
- IP address validation against Cloudonix ranges
- User-Agent verification for legitimate clients
- Rate limiting per source (100 requests/minute)
- Replay attack prevention with request ID tracking
- Optional signature verification for enhanced security

### Data Protection
- AES-256 encryption for sensitive data at rest
- TLS 1.3 encryption for data in transit
- PII anonymization for GDPR compliance
- Secure credential storage with automatic rotation

### Access Control
- Multi-tenant data isolation at database level
- Role-based access control with granular permissions
- Tenant-scoped API access with middleware enforcement
- Audit logging for all access attempts

## 📈 Monitoring

### Health Checks
- Application health: `GET /up`
- Detailed health: `GET /health`
- Database connectivity
- Redis connectivity
- External service availability

### Metrics
- Call volume and success rates
- Agent utilization and performance
- System resource usage
- Error rates and response times

### Logging
- Structured JSON logging
- Configurable log levels
- Automatic log rotation
- External log aggregation support

## 🚀 Deployment Options

### Docker Compose (Recommended)
```bash
# Development
docker-compose up -d

# Production
docker-compose -f docker-compose.prod.yml up -d
```

### Manual Deployment
```bash
# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Configure environment
cp .env.example .env
# Edit .env with production values

# Database setup
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start services
php artisan serve
```

### Cloud Platforms
The application can be deployed to:
- **AWS ECS/Fargate** with RDS and ElastiCache
- **Google Cloud Run** with Cloud SQL and Memorystore
- **Azure Container Instances** with Azure Database
- **DigitalOcean App Platform** with managed databases

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes with tests
4. Run the test suite: `docker-compose exec app php artisan test`
5. Commit your changes: `git commit -am 'Add some feature'`
6. Push to the branch: `git push origin feature/your-feature`
7. Submit a pull request

### Development Guidelines
- Follow PSR-12 coding standards
- Write comprehensive unit tests
- Update documentation for new features
- Use meaningful commit messages
- Test webhook integrations thoroughly

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Common Issues

**Port already in use**
```bash
# Find process using port
lsof -i :3000
# Kill the process
kill -9 <PID>
```

**Permission denied on Docker**
```bash
# Add user to docker group
sudo usermod -aG docker $USER
# Restart session
```

**Database connection failed**
```bash
# Check if database is running
docker-compose ps db
# View database logs
docker-compose logs db
```

### Getting Help
- Check the [troubleshooting guide](docs/troubleshooting.md)
- Review [API documentation](docs/api.md)
- Search existing [GitHub issues](https://github.com/your-repo/issues)

## 🎯 Roadmap

### Completed ✅
- WP1-10: Complete voice routing system with analytics, security, and deployment

### Future Enhancements 🔄
- Advanced ML-based routing algorithms
- Voice quality analytics and reporting
- Multi-region deployment support
- Advanced webhook transformation rules
- Real-time voice agent status monitoring

---

**Built with ❤️ for reliable voice AI agent routing**