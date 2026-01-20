#!/bin/bash

# MySQL Access Denied Fix - Database Reset Script
# This script performs a complete database reset to resolve MySQL access issues

set -e

echo "🔄 Starting complete database reset..."

# Stop all containers
echo "🛑 Stopping all containers..."
docker-compose down -v --remove-orphans

# Clean up any remaining containers/volumes
echo "🧹 Cleaning up Docker resources..."
docker system prune -f

# Remove any local database files
echo "🗑️ Removing local database files..."
rm -f backend/database/database.sqlite
rm -f backend/database/database.sqlite-*

# Start fresh containers
echo "🏗️ Starting fresh containers..."
docker-compose up -d --build

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 30

# Run migrations
echo "📋 Running database migrations..."
docker-compose exec app php artisan migrate:fresh --seed

echo "✅ Database reset complete!"
echo ""
echo "🔍 Check logs for any remaining issues:"
echo "docker-compose logs db"
echo "docker-compose logs app"