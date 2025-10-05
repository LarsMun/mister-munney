# 💰 Munney - Personal Finance Manager

Munney is een moderne persoonlijke geldbeheer applicatie gebouwd met Symfony en React. Import je banktransacties, categoriseer automatisch, en krijg inzicht in je uitgavenpatronen.

## ✨ Features

- 📊 **CSV Import**: Importeer banktransacties uit CSV bestanden
- 🏷️ **Auto-categorisatie**: Pattern matching voor automatische categorisatie
- 💳 **Multi-account**: Beheer meerdere bankrekeningen
- 💰 **Spaarrekeningen**: Track spaargeld apart
- 📈 **Budget Tracking**: Maandelijkse budgetten met versioning
- 📉 **Visualisaties**: TreeMap charts voor uitgaven inzicht
- 🔄 **Transactie Management**: Inline editing, bulk operations
- 🎯 **Nauwkeurig**: Money PHP library voor financiële precisie

## 🏗️ Tech Stack

### Backend
- **Framework**: Symfony 7.2
- **Language**: PHP 8.3
- **Database**: MySQL 8.0 + Doctrine ORM
- **Key Libraries**: 
  - Money PHP (financiële berekeningen)
  - League CSV (CSV parsing)
  - Nelmio CORS Bundle

### Frontend
- **Framework**: React 19
- **Language**: TypeScript
- **Build Tool**: Vite
- **Styling**: Tailwind CSS
- **UI Components**: Radix UI
- **Charts**: Recharts

### Infrastructure
- **Containerization**: Docker + Docker Compose
- **Development**: WSL2, PHPStorm
- **Production**: Synology NAS (Container Manager)

## 🚀 Quick Start

### Development (Lokaal)

```bash
# Clone repository
git clone https://github.com/YOUR-USERNAME/munney.git
cd munney

# Start containers
docker-compose up -d

# Wacht tot containers ready zijn
docker-compose ps

# Run database migrations
docker exec money-backend php bin/console doctrine:migrations:migrate

# Toegang applicatie
# Frontend: http://localhost:5173
# Backend API: http://localhost:8686
# Database: localhost:3333
```

### Production (Synology NAS)

Zie gedetailleerde instructies in [`synology/README.md`](./synology/README.md)

```bash
# Clone op NAS
cd /volume1/docker/munney-prod
git clone https://github.com/YOUR-USERNAME/munney.git .

# Configureer environment
cp .env.example .env
cp backend/.env.prod.example backend/.env.prod
cp frontend/.env.production.example frontend/.env.production
# Edit deze bestanden met je NAS IP en passwords

# Deploy
chmod +x synology/*.sh
./synology/deploy-prod.sh
```

## 📂 Project Structuur

```
munney/
├── backend/                # Symfony API
│   ├── src/
│   │   ├── Entity/         # Database entiteiten
│   │   ├── Account/        # Account domain
│   │   ├── Category/       # Categorieën
│   │   ├── Transaction/    # Transacties
│   │   ├── Pattern/        # Auto-categorisatie
│   │   └── Budget/         # Budget management
│   ├── migrations/         # Database migraties
│   ├── Dockerfile          # Development
│   └── Dockerfile.prod     # Production
│
├── frontend/               # React applicatie
│   ├── src/
│   │   ├── domains/        # Domain modules
│   │   ├── shared/         # Gedeelde componenten
│   │   ├── app/            # App-niveau code
│   │   └── lib/            # Libraries
│   ├── Dockerfile          # Development
│   └── Dockerfile.prod     # Production + Nginx
│
├── synology/               # Deployment scripts
│   ├── deploy-prod.sh      # Production deploy
│   ├── deploy-dev.sh       # Development deploy
│   ├── backup-db.sh        # Database backup
│   ├── restore-db.sh       # Database restore
│   └── README.md           # NAS deployment guide
│
├── docker-compose.yml      # Development setup
└── docker-compose.prod.yml # Production override
```

## 🔧 Development

### Backend Commands

```bash
# Database migrations
docker exec money-backend php bin/console doctrine:migrations:migrate

# Create nieuwe migration
docker exec money-backend php bin/console doctrine:migrations:generate

# Cache clearen
docker exec money-backend php bin/console cache:clear

# Tests runnen
docker exec money-backend php bin/phpunit
```

### Frontend Commands

```bash
# NPM commando's uitvoeren
docker exec money-frontend npm run [command]

# Logs bekijken
docker logs money-frontend -f
```

### Database Access

```bash
# Via Docker
docker exec -it money-mysql mysql -u money -p money_db

# Via externe client
Host: localhost
Port: 3333 (dev) of 3334 (prod)
User: money
Pass: ***REMOVED*** (dev) of je productie password
Database: money_db (dev) of money_db_prod (prod)
```

## 🌿 Git Workflow

### Branch Strategie

- `main` - Production branch (deployed op NAS)
- `develop` - Development branch (staging)
- `feature/*` - Feature branches
- `hotfix/*` - Hotfix branches

### Workflow

```bash
# Nieuwe feature
git checkout develop
git pull origin develop
git checkout -b feature/mijn-feature

# ... maak changes ...
git add .
git commit -m "feat: beschrijving"
git push origin feature/mijn-feature

# Pull Request naar develop
# Na review: merge naar develop

# Release naar production
git checkout main
git merge develop
git push origin main

# Deploy automatisch of handmatig op NAS
```

## 🔐 Environment Variables

### Development
Backend gebruikt `.env` en `.env.dev` (automatisch)
Frontend gebruikt Vite defaults

### Production
Configureer deze bestanden op je NAS:

**`.env`** (root):
```env
MYSQL_ROOT_PASSWORD=sterke_password
MYSQL_PASSWORD=andere_sterke_password
NAS_IP=192.168.1.XXX
```

**`backend/.env.prod`**:
```env
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=genereer-unieke-secret
CORS_ALLOW_ORIGIN=^https?://(192\.168\.1\.XXX)(:[0-9]+)?$
```

**`frontend/.env.production`**:
```env
VITE_API_URL=http://192.168.1.XXX:8687
```

## 💾 Database Backups

### Automatisch (Synology Task Scheduler)

1. Control Panel → Task Scheduler
2. Create → User-defined script
3. Schedule: Dagelijks 03:00
4. Script: `/volume1/docker/munney-prod/synology/backup-db.sh prod`

### Handmatig

```bash
cd /volume1/docker/munney-prod
./synology/backup-db.sh prod
```

Backups: `./backups/prod/munney_backup_YYYYMMDD_HHMMSS.sql.gz`

### Restore

```bash
./synology/restore-db.sh backups/prod/munney_backup_20250104_120000.sql.gz prod
```

## 🐛 Troubleshooting

### Containers starten niet

```bash
# Check logs
docker-compose logs

# Check individuele container
docker logs money-backend
docker logs money-frontend
docker logs money-mysql
```

### Database connectie errors

```bash
# Check of database container healthy is
docker-compose ps

# Test database connectie
docker exec money-mysql mysqladmin ping -h localhost
```

### Frontend kan backend niet bereiken

1. Check of backend draait: `curl http://localhost:8686/api/health`
2. Check CORS configuratie in `backend/.env`
3. Check VITE_API_URL in frontend environment

### Permission errors

```bash
# Fix backend var/ permissions
docker exec money-backend chown -R www-data:www-data /var/www/html/var
docker exec money-backend chmod -R 775 /var/www/html/var
```

## 📚 API Documentatie

OpenAPI documentatie beschikbaar op:
- Development: http://localhost:8686/api/doc
- Production: http://YOUR_NAS_IP:8687/api/doc

## 🤝 Contributing

1. Fork het project
2. Maak een feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit je changes (`git commit -m 'feat: Add some AmazingFeature'`)
4. Push naar branch (`git push origin feature/AmazingFeature`)
5. Open een Pull Request

## 📝 License

Dit is een persoonlijk project.

## 👤 Author

Lars - Munney Personal Finance Manager

---

**Versie**: 1.0  
**Laatste update**: Januari 2025