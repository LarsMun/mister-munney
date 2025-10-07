#!/bin/bash
#
# Munney Development Deployment Script voor Synology NAS
#
# Usage: ./synology/deploy-dev.sh
#
set -e  # Stop bij errors

# Kleuren voor output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Munney Development Deployment${NC}"
echo "========================================="

# Check of we in de juiste directory zijn
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}❌ Error: docker-compose.yml niet gevonden!${NC}"
    echo "Voer dit script uit vanuit de project root directory."
    exit 1
fi

# Pull laatste code van GitHub (als git repo)
if [ -d ".git" ]; then
    echo -e "${GREEN}📥 Pulling latest code from GitHub...${NC}"
    git fetch origin
    git checkout develop
    git pull origin develop
else
    echo -e "${YELLOW}⚠️  Geen git repository gevonden, skip git pull${NC}"
fi

# Stop bestaande containers
echo -e "${GREEN}🛑 Stopping existing containers...${NC}"
sudo docker compose down

# Build nieuwe images
echo -e "${GREEN}🏗️  Building development images...${NC}"
sudo docker compose build

# Start containers
echo -e "${GREEN}▶️  Starting development containers...${NC}"
sudo docker compose up -d

# Wacht tot database ready is
echo -e "${GREEN}⏳ Waiting for database to be ready...${NC}"
sleep 10

# Get backend container name (dynamisch)
BACKEND_CONTAINER=$(sudo docker compose ps -q backend)

# Run database migrations
echo -e "${GREEN}🗄️  Running database migrations...${NC}"
sudo docker exec $BACKEND_CONTAINER php bin/console doctrine:migrations:migrate --no-interaction

# Check container status
echo ""
echo -e "${GREEN}📊 Container Status:${NC}"
sudo docker compose ps

echo ""
echo -e "${GREEN}✅ Development deployment complete!${NC}"
echo ""
echo "🌐 Frontend: http://YOUR_NAS_IP:5173"
echo "🔌 Backend API: http://YOUR_NAS_IP:8686"
echo "🗄️  Database: YOUR_NAS_IP:3333"
echo ""
echo "📝 Logs bekijken:"
echo "   sudo docker compose logs backend -f"
echo "   sudo docker compose logs frontend -f"