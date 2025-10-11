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
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GREEN}🚀 Munney Development Deployment${NC}"
echo "========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "deploy/ubuntu/docker-compose.dev.yml" ]; then
    echo -e "${RED}❌ Error: docker-compose.dev.yml not found!${NC}"
    echo "Please run this script from /srv/munney-dev"
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ Error: .env file not found!${NC}"
    echo "Run setup-server.sh first"
    exit 1
fi

# Pull latest code
echo -e "${BLUE}📥 Pulling latest code from GitHub (develop branch)...${NC}"
git fetch origin
git checkout develop
git pull origin develop
echo -e "${GREEN}✅ Code updated${NC}"
echo ""

# Restart containers
echo -e "${BLUE}🔄 Restarting development containers...${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml down
docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml up -d --build
echo -e "${GREEN}✅ Containers restarted${NC}"
echo ""

# Container status
echo ""
echo -e "${GREEN}📊 Container Status:${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.dev.yml ps
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
echo "   docker logs munney-dev-backend -f"
echo "   docker logs munney-dev-frontend -f"
echo "   docker logs munney-dev-mysql -f"
echo ""
echo -e "${BLUE}📦 Filter containers:${NC}"
echo "   docker ps --filter 'label=project=munney'"
echo "   docker ps --filter 'label=environment=development'"
echo ""
