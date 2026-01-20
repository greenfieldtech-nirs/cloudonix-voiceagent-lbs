#!/bin/bash

# Cloudonix Voice Agent Load Balancer - Development Startup Script
# This script provides a one-command solution to start the entire development environment

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="Cloudonix Voice Agent Load Balancer"
DOCKER_COMPOSE_FILE="docker-compose.yml"
DOCKER_COMPOSE_OVERRIDE="docker-compose.override.yml"

# Function to print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker first."
        exit 1
    fi
}

# Function to check if docker-compose files exist
check_files() {
    if [ ! -f "$DOCKER_COMPOSE_FILE" ]; then
        print_error "Docker Compose file '$DOCKER_COMPOSE_FILE' not found."
        exit 1
    fi

    if [ ! -f "$DOCKER_COMPOSE_OVERRIDE" ]; then
        print_warning "Docker Compose override file '$DOCKER_COMPOSE_OVERRIDE' not found. Using base configuration only."
    fi
}

# Function to create .env files if they don't exist
setup_env() {
    if [ ! -f "backend/.env" ]; then
        print_info "Creating backend .env file..."
        cp backend/.env.example backend/.env
        print_success "Backend .env file created. Please review and update configuration."
    fi

    if [ ! -f "frontend/.env" ]; then
        print_info "Creating frontend .env file..."
        cp frontend/.env.example frontend/.env 2>/dev/null || echo "# Frontend environment variables" > frontend/.env
        print_success "Frontend .env file created."
    fi
}

# Function to build and start services
start_services() {
    print_info "Building and starting Docker services..."

    if [ -f "$DOCKER_COMPOSE_OVERRIDE" ]; then
        docker-compose -f $DOCKER_COMPOSE_FILE -f $DOCKER_COMPOSE_OVERRIDE up --build -d
    else
        docker-compose -f $DOCKER_COMPOSE_FILE up --build -d
    fi

    print_success "Services started successfully!"
}

# Function to wait for services to be healthy
wait_for_services() {
    print_info "Waiting for services to be healthy..."

    # Wait for database
    print_info "Waiting for database..."
    docker-compose exec -T db mysqladmin ping -h localhost --silent --wait=30

    # Wait for Laravel app
    print_info "Waiting for Laravel application..."
    timeout=60
    while [ $timeout -gt 0 ]; do
        if docker-compose exec -T app curl -f http://localhost:9000/up > /dev/null 2>&1; then
            break
        fi
        sleep 2
        timeout=$((timeout - 2))
    done

    if [ $timeout -le 0 ]; then
        print_warning "Laravel app health check timed out, but services may still be starting..."
    fi

    print_success "Services health check completed!"
}

# Function to run initial setup
run_setup() {
    print_info "Running initial setup..."

    # Run database migrations
    print_info "Running database migrations..."
    docker-compose exec -T app php artisan migrate --force

    # Run database seeding (optional)
    if [ "$RUN_SEEDING" = "true" ]; then
        print_info "Running database seeding..."
        docker-compose exec -T app php artisan db:seed --force
    fi

    # Clear and cache config
    print_info "Clearing and caching Laravel configuration..."
    docker-compose exec -T app php artisan config:cache
    docker-compose exec -T app php artisan route:cache
    docker-compose exec -T app php artisan view:cache

    # Install frontend dependencies (if node_modules doesn't exist)
    if [ ! -d "frontend/node_modules" ]; then
        print_info "Installing frontend dependencies..."
        docker-compose exec -T web npm install
    fi

    print_success "Initial setup completed!"
}

# Function to display service URLs
show_urls() {
    echo
    print_success "🎉 $PROJECT_NAME is now running!"
    echo
    echo "Service URLs:"
    echo "  🌐 Web Application:    http://localhost"
    echo "  🔧 Laravel API:        http://localhost:8000"
    echo "  ⚛️  React Dev Server:   http://localhost:3000"
    echo "  🗄️  Database:           localhost:3306 (root/password)"
    echo "  🔴 Redis:              localhost:6379"
    echo "  📦 MinIO:              http://localhost:9000 (minioadmin/minioadmin)"
    echo "  📧 MailHog:            http://localhost:8025 (if running)"
    echo
    echo "Useful commands:"
    echo "  🛑 Stop services:       ./scripts/stop.sh"
    echo "  📋 View logs:           docker-compose logs -f"
    echo "  🔄 Restart service:     docker-compose restart <service>"
    echo "  🧪 Run tests:           docker-compose exec app php artisan test"
    echo
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo
    echo "Start the Cloudonix Voice Agent Load Balancer development environment"
    echo
    echo "Options:"
    echo "  --help, -h          Show this help message"
    echo "  --seed              Run database seeding after setup"
    echo "  --no-setup          Skip initial setup (migrations, etc.)"
    echo "  --profiles PROFILES Start additional services (comma-separated: ngrok,queue,scheduler,mail)"
    echo
    echo "Examples:"
    echo "  $0                           # Start basic services"
    echo "  $0 --seed                    # Start with database seeding"
    echo "  $0 --profiles ngrok,mail     # Start with ngrok and MailHog"
    echo
}

# Parse command line arguments
RUN_SEEDING=false
SKIP_SETUP=false
PROFILES=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_usage
            exit 0
            ;;
        --seed)
            RUN_SEEDING=true
            shift
            ;;
        --no-setup)
            SKIP_SETUP=true
            shift
            ;;
        --profiles)
            PROFILES="$2"
            shift 2
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Main execution
main() {
    echo
    print_info "🚀 Starting $PROJECT_NAME Development Environment"
    echo

    # Pre-flight checks
    check_docker
    check_files

    # Setup environment
    setup_env

    # Start services
    start_services

    # Wait for services
    wait_for_services

    # Run initial setup
    if [ "$SKIP_SETUP" = "false" ]; then
        run_setup
    fi

    # Show service information
    show_urls

    # Start additional profiles if requested
    if [ -n "$PROFILES" ]; then
        print_info "Starting additional services: $PROFILES"
        docker-compose --profile $PROFILES up -d
    fi

    print_success "🎯 Development environment is ready!"
    echo
}

# Run main function
main "$@"