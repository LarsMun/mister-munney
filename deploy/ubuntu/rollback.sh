#!/bin/bash
# Rollback script for Mister Munney production deployment
# This script restores the previous version of the application

set -e

DEPLOY_DIR="/srv/munney-prod"
COMPOSE_FILE="deploy/ubuntu/docker-compose.prod.yml"

echo "========================================"
echo "🔄 Starting rollback procedure..."
echo "========================================"

cd "$DEPLOY_DIR"

# Check if rollback images exist
if ! docker images | grep -q "munney-prod-backend:rollback"; then
    echo "❌ No rollback images found!"
    echo "   Rollback images are created during deployment."
    exit 1
fi

echo "📦 Rollback images found:"
docker images | grep "munney-prod.*:rollback" || true

# Stop current containers
echo ""
echo "🛑 Stopping current containers..."
docker compose -f "$COMPOSE_FILE" down

# Restore rollback images
echo ""
echo "🔄 Restoring previous images..."
docker tag munney-prod-backend:rollback munney-prod-backend:latest
docker tag munney-prod-frontend:rollback munney-prod-frontend:latest
echo "✅ Images restored"

# Start containers with previous version
echo ""
echo "🚀 Starting containers with previous version..."
docker compose -f "$COMPOSE_FILE" up -d

# Wait for services to start
echo ""
echo "⏳ Waiting for services to stabilize..."
sleep 15

# Clear cache
echo ""
echo "🧹 Clearing cache..."
docker exec munney-prod-backend php bin/console cache:clear --env=prod || true
docker exec munney-prod-backend php bin/console cache:warmup --env=prod || true

# Health check
echo ""
echo "🏥 Running health checks..."
sleep 5

FRONTEND_OK=false
BACKEND_OK=false

if curl -f -s https://munney.munne.me/ > /dev/null; then
    echo "✅ Frontend responding"
    FRONTEND_OK=true
else
    echo "❌ Frontend check failed"
fi

if curl -f -s https://munney.munne.me/api/health > /dev/null; then
    echo "✅ Backend API responding"
    BACKEND_OK=true
else
    echo "❌ Backend API check failed"
fi

echo ""
echo "========================================"
if $FRONTEND_OK && $BACKEND_OK; then
    echo "✅ Rollback completed successfully!"
    echo "========================================"
    echo "🌐 URL: https://munney.munne.me"
    docker ps --filter 'label=project=munney' --filter 'label=environment=production'
    exit 0
else
    echo "⚠️  Rollback completed but health checks failed"
    echo "========================================"
    echo "Manual intervention may be required."
    docker ps --filter 'label=project=munney' --filter 'label=environment=production'
    exit 1
fi
