#!/bin/bash
#
# Munney Production Deployment Script voor Synology NAS
#
# Usage: ./synology/deploy-prod.sh
#

set -e  # Stop bij errors

# Kleuren voor output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Munney Production Deployment${NC}"
echo "========================================="

# Check of we in de juiste directory zijn
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ Error: docker-compose.yml niet gevonden!${NC}"
    echo "Voer dit script uit vanuit de project root directory."
    exit 1
fi

# Check of .env bestaat
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ Error: .env bestand niet gevonden!${NC}"
    echo "Kopieer .env.example naar .env en vul de waarden in."
    exit 1
fi

# Vraag bevestiging
echo -e "${YELLOW}⚠️  Dit zal de productie omgeving (her)starten.${NC}"
read -p "Doorgaan? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment geannuleerd."
    exit 0
fi

# Pull laatste code van GitHub (als git repo)
if [ -d ".git" ]; then
    echo -e "${GREEN}📥 Pulling latest code from GitHub...${NC}"
    git fetch origin
    git checkout main
    git pull origin main
else
    echo -e "${YELLOW}⚠️  Geen git repository gevonden, skip git pull${NC}"
fi

# Stop bestaande containers
echo -e "${GREEN}🛑 Stopping existing containers...${NC}"
docker-compose -f docker-compose.yml -f docker-compose.prod.yml down

# Build nieuwe images
echo -e "${GREEN}🏗️  Building production images...${NC}"
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build --no-cache

# Start containers
echo -e "${GREEN}▶️  Starting production containers...${NC}"
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Wacht tot database ready is
echo -e "${GREEN}⏳ Waiting for database to be ready...${NC}"
sleep 10

# Run database migrations
echo -e "${GREEN}🗄️  Running database migrations...${NC}"
docker exec munney-backend-prod php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Clear Symfony cache
echo -e "${GREEN}🧹 Clearing Symfony cache...${NC}"
docker exec munney-backend-prod php bin/console cache:clear --env=prod

# Warm up cache
echo -e "${GREEN}🔥 Warming up cache...${NC}"
docker exec munney-backend-prod php bin/console cache:warmup --env=prod

# Check container status
echo ""
echo -e "${GREEN}📊 Container Status:${NC}"
docker-compose -f docker-compose.yml -f docker-compose.prod.yml ps

echo ""
echo -e "${GREEN}✅ Production deployment complete!${NC}"
echo ""
echo "🌐 Frontend: http://YOUR_NAS_IP:3001"
echo "🔌 Backend API: http://YOUR_NAS_IP:8687"
echo "🗄️  Database: YOUR_NAS_IP:3334"
echo ""
echo "📝 Logs bekijken:"
echo "   docker logs munney-backend-prod -f"
echo "   docker logs munney-frontend-prod -f"
echo ""