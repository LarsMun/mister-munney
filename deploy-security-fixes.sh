#!/bin/bash
# Security Hardening Deployment Script
# Applies CORS fixes and rate limiting configuration

set -e  # Exit on error

echo "=================================="
echo "Security Hardening Deployment"
echo "=================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}This script will:${NC}"
echo "1. Restart backend container with new CORS configuration"
echo "2. Clear Symfony cache"
echo "3. Verify services are running"
echo "4. Test CORS configuration"
echo ""

read -p "Continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Step 1: Restarting backend container..."
docker restart munney-backend-prod

echo "Waiting for backend to be ready (10 seconds)..."
sleep 10

echo ""
echo "Step 2: Clearing Symfony cache..."
docker exec munney-backend-prod php bin/console cache:clear

echo ""
echo "Step 3: Verifying containers are running..."
if docker ps | grep -q "munney-backend-prod"; then
    echo -e "${GREEN}✓ Backend container running${NC}"
else
    echo -e "${RED}✗ Backend container not running!${NC}"
    exit 1
fi

if docker ps | grep -q "munney-frontend-prod"; then
    echo -e "${GREEN}✓ Frontend container running${NC}"
else
    echo -e "${RED}✗ Frontend container not running!${NC}"
    exit 1
fi

if docker ps | grep -q "munney-mysql-prod"; then
    echo -e "${GREEN}✓ Database container running${NC}"
else
    echo -e "${RED}✗ Database container not running!${NC}"
    exit 1
fi

echo ""
echo "Step 4: Testing CORS configuration..."

# Test with correct origin
CORS_RESPONSE=$(curl -s -I -H "Origin: https://munney.munne.me" https://munney.munne.me/api/feature-flags 2>/dev/null | grep -i "access-control-allow-origin" || true)

if [[ -n "$CORS_RESPONSE" ]]; then
    echo -e "${GREEN}✓ CORS headers present${NC}"
    echo "  Response: $CORS_RESPONSE"
else
    echo -e "${YELLOW}⚠ CORS headers not found (might be OK if Traefik is handling it)${NC}"
fi

echo ""
echo -e "${GREEN}=================================="
echo "Deployment Complete!"
echo "==================================${NC}"
echo ""
echo "Next steps:"
echo "1. Visit https://munney.munne.me and verify the app works"
echo "2. Check browser console for any CORS errors (F12)"
echo "3. Review logs: docker logs munney-backend-prod --tail 50"
echo "4. ${RED}IMPORTANT: Rotate your OpenAI API key${NC}"
echo "   - Get new key from https://platform.openai.com/api-keys"
echo "   - Update .env and backend/.env"
echo "   - Run: docker restart munney-backend-prod"
echo "   - Revoke old key on OpenAI platform"
echo ""
echo "Full details in: SECURITY_HARDENING_SUMMARY.md"
echo ""
