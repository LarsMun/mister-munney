#!/bin/bash
#
# Munney Database Backup Script
#
# Usage: ./synology/backup-db.sh [prod|dev]
#
# Dit script maakt een backup van de MySQL database
# en kan ingepland worden via Synology Task Scheduler
#

set -e

# Kleuren voor output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Load environment variables from .env file
if [ -f ".env" ]; then
    export $(grep -v '^#' .env | xargs)
fi

# Bepaal welke omgeving (default: prod)
ENV=${1:-prod}

if [ "$ENV" = "prod" ]; then
    CONTAINER="munney-prod-mysql"
    DB_NAME="money_db_prod"
    DB_PASSWORD="${MYSQL_PASSWORD_PROD}"
    BACKUP_DIR="./backups/prod"
else
    CONTAINER="money-mysql"
    DB_NAME="money_db"
    DB_PASSWORD="moneymakestheworldgoround"
    BACKUP_DIR="./backups/dev"
fi

# Check if password is set
if [ -z "$DB_PASSWORD" ]; then
    echo -e "${RED}❌ Error: Database password niet gevonden!${NC}"
    echo "Voor prod: Zorg dat MYSQL_PASSWORD_PROD is ingesteld in .env"
    exit 1
fi

# Maak backup directory aan
mkdir -p "$BACKUP_DIR"

# Datum voor filename
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/munney_backup_${TIMESTAMP}.sql"

echo -e "${GREEN}🗄️  Munney Database Backup${NC}"
echo "========================================="
echo "Environment: $ENV"
echo "Container: $CONTAINER"
echo "Database: $DB_NAME"
echo "Backup file: $BACKUP_FILE"
echo ""

# Check of container draait
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo -e "${RED}❌ Error: Container $CONTAINER is niet actief!${NC}"
    exit 1
fi

# Maak backup
echo -e "${GREEN}💾 Creating backup...${NC}"
docker exec $CONTAINER mysqldump \
    -u money \
    -p"$DB_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --databases $DB_NAME \
    > "$BACKUP_FILE"

# Comprimeer backup
echo -e "${GREEN}🗜️  Compressing backup...${NC}"
gzip "$BACKUP_FILE"
BACKUP_FILE="${BACKUP_FILE}.gz"

# Toon resultaat
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo ""
echo -e "${GREEN}✅ Backup succesvol aangemaakt!${NC}"
echo "📁 Bestand: $BACKUP_FILE"
echo "💾 Grootte: $BACKUP_SIZE"

# Cleanup oude backups (behoud laatste 7 dagen)
echo ""
echo -e "${YELLOW}🧹 Cleaning up old backups (keeping last 7 days)...${NC}"
find "$BACKUP_DIR" -name "munney_backup_*.sql.gz" -mtime +7 -delete
REMAINING=$(find "$BACKUP_DIR" -name "munney_backup_*.sql.gz" | wc -l)
echo "📊 Aantal backups in $BACKUP_DIR: $REMAINING"

echo ""
echo -e "${GREEN}✅ Backup proces voltooid!${NC}"