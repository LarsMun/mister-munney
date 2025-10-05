#!/bin/bash
#
# Munney Database Restore Script
#
# Usage: ./synology/restore-db.sh <backup-file> [prod|dev]
#
#Voorbeeld: ./synology/restore-db.sh backups/prod/munney_backup_20250104_120000.sql.gz prod
#

set -e

# Kleuren voor output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check argumenten
if [ -z "$1" ]; then
    echo -e "${RED}❌ Error: Geen backup bestand opgegeven!${NC}"
    echo ""
    echo "Usage: $0 <backup-file> [prod|dev]"
    echo ""
    echo "Beschikbare backups:"
    find ./backups -name "*.sql.gz" -type f 2>/dev/null | sort -r | head -10
    exit 1
fi

BACKUP_FILE="$1"
ENV=${2:-prod}

# Check of backup bestaat
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ Error: Backup bestand niet gevonden: $BACKUP_FILE${NC}"
    exit 1
fi

if [ "$ENV" = "prod" ]; then
    CONTAINER="munney-mysql-prod"
    DB_NAME="money_db_prod"
else
    CONTAINER="money-mysql"
    DB_NAME="money_db"
fi

echo -e "${YELLOW}⚠️  WAARSCHUWING: Database Restore${NC}"
echo "========================================="
echo "Environment: $ENV"
echo "Container: $CONTAINER"
echo "Database: $DB_NAME"
echo "Backup file: $BACKUP_FILE"
echo ""
echo -e "${RED}Dit zal ALLE huidige data in de database OVERSCHRIJVEN!${NC}"
echo ""
read -p "Weet je het ZEKER? Type 'yes' om door te gaan: " -r
echo
if [ "$REPLY" != "yes" ]; then
    echo "Restore geannuleerd."
    exit 0
fi

# Check of container draait
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo -e "${RED}❌ Error: Container $CONTAINER is niet actief!${NC}"
    exit 1
fi

# Maak eerst een safety backup van huidige data
SAFETY_BACKUP="./backups/pre_restore_$(date +"%Y%m%d_%H%M%S").sql.gz"
echo -e "${GREEN}📦 Making safety backup of current data...${NC}"
docker exec $CONTAINER mysqldump \
    -u money \
    -p***REMOVED*** \
    --single-transaction \
    --databases $DB_NAME \
    | gzip > "$SAFETY_BACKUP"
echo "✅ Safety backup: $SAFETY_BACKUP"
echo ""

# Restore database
echo -e "${GREEN}♻️  Restoring database...${NC}"

if [[ "$BACKUP_FILE" == *.gz ]]; then
    # Gecomprimeerd bestand
    gunzip < "$BACKUP_FILE" | docker exec -i $CONTAINER mysql \
        -u money \
        -p***REMOVED***
else
    # Niet gecomprimeerd bestand
    docker exec -i $CONTAINER mysql \
        -u money \
        -p***REMOVED*** \
        < "$BACKUP_FILE"
fi

echo ""
echo -e "${GREEN}✅ Database succesvol hersteld!${NC}"
echo ""
echo "🔄 Vergeet niet om Symfony cache te clearen:"
echo "   docker exec munney-backend-prod php bin/console cache:clear --env=prod"