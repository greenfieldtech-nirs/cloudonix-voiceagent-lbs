#!/bin/bash

# Cloudonix Voice Agent Load Balancer - Setup Script
# This script helps configure the development environment

set -e

echo "🚀 Cloudonix Voice Agent Load Balancer Setup"
echo "=============================================="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Copy environment file
if [ ! -f "backend/.env" ]; then
    echo "📋 Copying environment configuration..."
    cp backend/.env.sample backend/.env
    echo "✅ Created backend/.env from template"
    echo "⚠️  Please edit backend/.env with your actual configuration values"
else
    echo "ℹ️  backend/.env already exists, skipping copy"
fi

# Generate Laravel application key if not set
if ! grep -q "APP_KEY=[^base64:]" backend/.env; then
    echo "🔑 Generating Laravel application key..."
    docker-compose run --rm app php artisan key:generate
    echo "✅ Laravel application key generated"
fi

# Check if ngrok is installed
if command -v ngrok &> /dev/null; then
    echo "📡 ngrok is installed"

    # Configure ngrok with auth token (user needs to set this up)
    echo "⚠️  To configure ngrok:"
    echo "   1. Get your auth token from: https://dashboard.ngrok.com/get-started/your-authtoken"
    echo "   2. Run: ngrok config add-authtoken YOUR_TOKEN_HERE"
    echo "   3. Then run: ngrok http 80"
    echo "   4. Use the HTTPS URL for Cloudonix webhook configuration"
else
    echo "ℹ️  ngrok is not installed. Install from: https://ngrok.com/download"
fi

echo ""
echo "🎯 Next Steps:"
echo "1. Edit backend/.env with your configuration"
echo "2. Run: docker-compose up --build"
echo "3. Run: docker-compose exec app php artisan migrate"
echo "4. Access the application at: http://localhost:3000"
echo "5. Set up ngrok for webhook testing if needed"

echo ""
echo "📚 Useful Commands:"
echo "• View logs: docker-compose logs -f"
echo "• Access Laravel: docker-compose exec app bash"
echo "• Access database: docker-compose exec db mysql -u root -p cloudonix_voiceagent_lbs"
echo "• Run tests: docker-compose exec app php artisan test"

echo ""
echo "✅ Setup complete! Ready to start development."