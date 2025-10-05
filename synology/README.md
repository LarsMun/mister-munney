# Munney - Synology NAS Deployment Guide

Deze gids helpt je met het deployen van Munney op je Synology NAS.

## 📋 Vereisten

- Synology NAS met DSM 7.0+
- **Container Manager** geïnstalleerd via Package Center
- SSH toegang tot je NAS
- Git geïnstalleerd (optioneel, voor automatische updates)

## 🚀 Eerste Keer Setup

### Stap 1: Maak Directories aan

SSH naar je NAS en maak de benodigde directories:

```bash
# SSH naar je NAS
ssh admin@YOUR_NAS_IP

# Maak directories aan
sudo mkdir -p /volume1/docker/munney-prod
sudo mkdir -p /volume1/docker/munney-dev
sudo mkdir -p /volume1/backups/munney

# Geef jezelf ownership
sudo chown -R $(whoami):users /volume1/docker/munney-prod
sudo chown -R $(whoami):users /volume1/docker/munney-dev
sudo chown -R $(whoami):users /volume1/backups/munney
```

### Stap 2: Clone Repository

```bash
# Development omgeving
cd /volume1/docker/munney-dev
git clone https://github.com/YOUR-USERNAME/munney.git .
git checkout develop

# Production omgeving
cd /volume1/docker/munney-prod
git clone https://github.com/YOUR-USERNAME/munney.git .
git checkout main
```

### Stap 3: Configureer Environment Variables

#### Production:
```bash
cd /volume1/docker/munney-prod

# Kopieer environment template
cp .env.example .env

# Edit met je NAS IP en sterke passwords
nano .env
```

Vul in:
```env
MYSQL_ROOT_PASSWORD=JeSterkeMySQLRootPassword123!
MYSQL_PASSWORD=JeSterkeMySQLUserPassword456!
NAS_IP=192.168.1.XXX  # Vervang met je echte NAS IP
BACKUP_DIR=/volume1/backups/munney
```

#### Backend productie config:
```bash
cp backend/.env.prod.example backend/.env.prod
nano backend/.env.prod
```

Update:
- `APP_SECRET` met een unieke waarde
- `CORS_ALLOW_ORIGIN` met je NAS IP range
- Database password moet matchen met `.env`

#### Frontend productie config:
```bash
cp frontend/.env.production.example frontend/.env.production
nano frontend/.env.production
```

Update `VITE_API_URL` met je NAS IP:
```env
VITE_API_URL=http://192.168.1.XXX:8687
```

### Stap 4: Maak Scripts Executable

```bash
chmod +x synology/*.sh
```

### Stap 5: Deploy!

#### Production:
```bash
cd /volume1/docker/munney-prod
./synology/deploy-prod.sh
```

#### Development:
```bash
cd /volume1/docker/munney-dev
./synology/deploy-dev.sh
```

## 🌐 Toegang tot Applicatie

### Production:
- **Frontend**: http://YOUR_NAS_IP:3001
- **Backend API**: http://YOUR_NAS_IP:8687
- **Database**: YOUR_NAS_IP:3334

### Development:
- **Frontend**: http://YOUR_NAS_IP:5173
- **Backend API**: http://YOUR_NAS_IP:8686
- **Database**: YOUR_NAS_IP:3333

## 🔄 Updates Deployen

```bash
cd /volume1/docker/munney-prod
./synology/deploy-prod.sh
```

Het script doet automatisch:
1. Pull laatste code van GitHub
2. Stop oude containers
3. Build nieuwe images
4. Start containers
5. Run database migrations
6. Clear cache

## 💾 Database Backups

### Handmatige Backup

```bash
cd /volume1/docker/munney-prod
./synology/backup-db.sh prod
```

Backups worden opgeslagen in: `./backups/prod/`

### Automatische Backups via Task Scheduler

1. Open **Control Panel** → **Task Scheduler**
2. Create → **Scheduled Task** → **User-defined script**
3. Configuratie:
   - **Task**: Munney DB Backup
   - **User**: root
   - **Schedule**: Dagelijks om 03:00
   - **Script**:
   ```bash
   cd /volume1/docker/munney-prod
   /bin/bash ./synology/backup-db.sh prod
   ```

### Database Restore

```bash
cd /volume1/docker/munney-prod

# Bekijk beschikbare backups
ls -lh backups/prod/

# Restore specifieke backup
./synology/restore-db.sh backups/prod/munney_backup_20250104_120000.sql.gz prod
```

## 🔍 Troubleshooting

### Containers status bekijken

```bash
cd /volume1/docker/munney-prod
docker-compose -f docker-compose.yml -f docker-compose.prod.yml ps
```

### Logs bekijken

```bash
# Backend logs
docker logs munney-backend-prod -f

# Frontend logs
docker logs munney-frontend-prod -f

# Database logs
docker logs munney-mysql-prod -f
```

### Container herstarten

```bash
docker restart munney-backend-prod
docker restart munney-frontend-prod
```

### Database verbinding testen

```bash
docker exec -it munney-mysql-prod mysql -u money -p money_db_prod
# Voer password in wanneer gevraagd
```

### Symfony cache problemen

```bash
docker exec munney-backend-prod php bin/console cache:clear --env=prod
docker exec munney-backend-prod php bin/console cache:warmup --env=prod
```

### Alle containers stoppen en opnieuw starten

```bash
cd /volume1/docker/munney-prod
docker-compose -f docker-compose.yml -f docker-compose.prod.yml down
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## 🔐 Reverse Proxy Setup (Optioneel)

### Via Synology Reverse Proxy

1. Open **Control Panel** → **Login Portal** → **Advanced** → **Reverse Proxy**
2. Create nieuwe regel:

**Frontend:**
- Source: `munney.local` (of subdomain)
- Port: 443 (HTTPS) of 80 (HTTP)
- Destination: `localhost:3001`

**Backend API:**
- Source: `munney-api.local`
- Port: 443 of 80
- Destination: `localhost:8687`

3. Update `frontend/.env.production`:
```env
VITE_API_URL=http://munney-api.local
```

4. Update `backend/.env.prod` CORS:
```env
CORS_ALLOW_ORIGIN='^https?://(munney\.local|localhost)(:[0-9]+)?$'
```

5. Redeploy: `./synology/deploy-prod.sh`

## 📊 Monitoring

### Disk Usage

```bash
# Check database grootte
docker exec munney-mysql-prod du -sh /var/lib/mysql

# Check backup grootte
du -sh /volume1/backups/munney
```

### Resource Usage

Via Container Manager in DSM of:

```bash
docker stats munney-backend-prod munney-frontend-prod munney-mysql-prod
```

## 🆘 Support

- Check de logs: `docker logs <container-name> -f`
- Controleer environment variabelen zijn correct ingesteld
- Verify database verbinding
- Check firewalls/poorten zijn open

---

**Gemaakt voor Munney v1.0**  
Laatste update: Januari 2025