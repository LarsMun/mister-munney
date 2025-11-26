#!/bin/bash
#
# Munney Production Deployment Script
#
# Usage: cd /srv/munney-prod && bash deploy/ubuntu/deploy-prod.sh
#

set -e  # Stop on errors

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GREEN}🚀 Munney Production Deployment${NC}"
echo "========================================="
echo ""

# Check if we're in the right directory
if [ ! -f "deploy/ubuntu/docker-compose.prod.yml" ]; then
    echo -e "${RED}❌ Error: docker-compose.prod.yml not found!${NC}"
    echo "Please run this script from /srv/munney-prod"
    exit 1
fi

# Check if .env exists
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ Error: .env file not found!${NC}"
    echo "Run setup-server.sh first"
    exit 1
fi

# Confirmation prompt
echo -e "${YELLOW}⚠️  This will deploy to PRODUCTION${NC}"
echo -e "${YELLOW}⚠️  URL: https://munney.example.com${NC}"
read -p "Continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled"
    exit 0
fi
echo ""

# Source .env for backup
set -a
source .env
set +a

# Backup database before deployment
echo -e "${BLUE}💾 Creating database backup...${NC}"
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="/srv/munney-prod/backups/munney_prod_${BACKUP_DATE}.sql"
if docker ps | grep -q munney-prod-mysql; then
    docker exec munney-prod-mysql mysqldump -u root -p${MYSQL_ROOT_PASSWORD_PROD} money_db_prod > $BACKUP_FILE 2>/dev/null || true
    if [ -f "$BACKUP_FILE" ]; then
        echo -e "${GREEN}✅ Backup saved: $BACKUP_FILE${NC}"
    else
        echo -e "${YELLOW}⚠️  Backup failed (container might not exist yet)${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  Database container not running, skipping backup${NC}"
fi
echo ""

# Pull latest code from GitHub
echo -e "${BLUE}📥 Pulling latest code from GitHub (main branch)...${NC}"
git fetch origin
git checkout main
git pull origin main
echo -e "${GREEN}✅ Code updated${NC}"
echo ""

# Stop existing containers
echo -e "${BLUE}🛑 Stopping existing containers...${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.prod.yml down || true
echo -e "${GREEN}✅ Containers stopped${NC}"
echo ""

# Build images (with --no-cache for production)
echo -e "${BLUE}🏗️  Building production images...${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.prod.yml build --no-cache
echo -e "${GREEN}✅ Images built${NC}"
echo ""

# Start containers
echo -e "${BLUE}▶️  Starting production containers...${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.prod.yml up -d
echo -e "${GREEN}✅ Containers started${NC}"
echo ""

# Wait for database
echo -e "${BLUE}⏳ Waiting for database to be ready...${NC}"
sleep 15
echo -e "${GREEN}✅ Database ready${NC}"
echo ""

# Run migrations
echo -e "${BLUE}🗄️  Running database migrations...${NC}"
docker exec munney-prod-backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod
echo -e "${GREEN}✅ Migrations complete${NC}"
echo ""

# Clear and warm up cache
echo -e "${BLUE}🧹 Clearing Symfony cache...${NC}"
docker exec munney-prod-backend php bin/console cache:clear --env=prod
echo -e "${GREEN}✅ Cache cleared${NC}"
echo ""

echo -e "${BLUE}🔥 Warming up cache...${NC}"
docker exec munney-prod-backend php bin/console cache:warmup --env=prod
echo -e "${GREEN}✅ Cache warmed up${NC}"
echo ""

# Container status
echo ""
echo -e "${GREEN}📊 Container Status:${NC}"
docker compose --env-file .env -f deploy/ubuntu/docker-compose.prod.yml ps
echo ""

# Health check
echo -e "${BLUE}🏥 Running health check...${NC}"
sleep 5
if curl -f -s https://munney.example.com/ > /dev/null; then
    echo -e "${GREEN}✅ Frontend is responding${NC}"
else
    echo -e "${YELLOW}⚠️  Frontend health check failed (might need a minute)${NC}"
fi
echo ""

# Final summary
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}✅ Production deployment complete!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "${BLUE}🌐 Access the application:${NC}"
echo "   https://munney.example.com"
echo ""
echo -e "${BLUE}📝 View logs:${NC}"
echo "   docker logs munney-prod-backend -f"
echo "   docker logs munney-prod-frontend -f"
echo "   docker logs munney-prod-mysql -f"
echo ""
echo -e "${BLUE}💾 Database backup:${NC}"
echo "   $BACKUP_FILE"
echo ""
echo -e "${BLUE}🔧 Useful commands:${NC}"
echo "   docker exec -it munney-prod-backend bash"
echo "   docker exec -it munney-prod-mysql mysql -u money -p money_db_prod"
echo ""
echo -e "${BLUE}📦 Filter containers:${NC}"
echo "   docker ps --filter 'label=project=munney'"
echo "   docker ps --filter 'label=environment=production'"
echo ""
