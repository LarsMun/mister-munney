#!/bin/bash
#
# Munney Development Deployment Script
#
# Usage: cd /srv/munney-dev && bash deploy/ubuntu/deploy-dev.sh
#

set -e  # Stop on errors

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GREEN}🚀 Munney Development Deployment${NC}"
echo "========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ Error: docker-compose.yml not found!${NC}"
    echo "Please run this script from /srv/munney-dev"
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ Error: .env file not found!${NC}"
    echo "Run setup-server.sh first"
    exit 1
fi

# Pull latest code from GitHub
echo -e "${BLUE}📥 Pulling latest code from GitHub (develop branch)...${NC}"
git fetch origin
git checkout develop
git pull origin develop
echo -e "${GREEN}✅ Code updated${NC}"
echo ""

# Stop existing containers
echo -e "${BLUE}🛑 Stopping existing containers...${NC}"
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.dev.yml down || true
echo -e "${GREEN}✅ Containers stopped${NC}"
echo ""

# Build images
echo -e "${BLUE}🏗️  Building development images...${NC}"
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.dev.yml build
echo -e "${GREEN}✅ Images built${NC}"
echo ""

# Start containers
echo -e "${BLUE}▶️  Starting development containers...${NC}"
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.dev.yml up -d
echo -e "${GREEN}✅ Containers started${NC}"
echo ""

# Wait for database
echo -e "${BLUE}⏳ Waiting for database to be ready...${NC}"
sleep 15
echo -e "${GREEN}✅ Database ready${NC}"
echo ""

# Install/update Composer dependencies
echo -e "${BLUE}📦 Installing Composer dependencies...${NC}"
docker exec munney-backend-dev composer install --optimize-autoloader
echo -e "${GREEN}✅ Dependencies installed${NC}"
echo ""

# Run migrations
echo -e "${BLUE}🗄️  Running database migrations...${NC}"
docker exec munney-backend-dev php bin/console doctrine:migrations:migrate --no-interaction --env=dev || true
echo -e "${GREEN}✅ Migrations complete${NC}"
echo ""

# Clear cache
echo -e "${BLUE}🧹 Clearing Symfony cache...${NC}"
docker exec munney-backend-dev php bin/console cache:clear --env=dev
echo -e "${GREEN}✅ Cache cleared${NC}"
echo ""

# Container status
echo ""
echo -e "${GREEN}📊 Container Status:${NC}"
docker compose -f docker-compose.yml -f deploy/ubuntu/docker-compose.dev.yml ps
echo ""

# Final summary
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ Development deployment complete!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "${BLUE}🌐 Access the application:${NC}"
echo "   https://devmunney.home.munne.me"
echo ""
echo -e "${BLUE}📝 View logs:${NC}"
echo "   docker logs munney-backend-dev -f"
echo "   docker logs munney-frontend-dev -f"
echo "   docker logs munney-mysql-dev -f"
echo ""
echo -e "${BLUE}🔧 Useful commands:${NC}"
echo "   docker exec -it munney-backend-dev bash"
echo "   docker exec -it munney-mysql-dev mysql -u money -p money_db_dev"
echo ""