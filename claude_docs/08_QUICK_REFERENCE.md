# Quick Reference Card

## URLs

| Environment | Frontend | Backend API |
|-------------|----------|-------------|
| Local | http://localhost:3000 | http://localhost:8787/api |
| Development | https://devmunney.home.munne.me | https://devmunney.home.munne.me/api |
| Production | https://munney.munne.me | https://munney.munne.me/api |

## Docker Commands

```bash
# === LOCAL DEVELOPMENT ===

# Start all services
docker compose up -d

# Stop all services
docker compose down

# View logs (all services)
docker compose logs -f

# View specific service logs
docker compose logs -f backend
docker compose logs -f frontend

# Rebuild containers
docker compose build --no-cache
docker compose up -d

# Enter container shell
docker exec -it money-backend bash
docker exec -it money-frontend sh
docker exec -it money-mysql bash

# === SERVER (SSH into server first) ===

# Development
cd /srv/munney-dev
docker compose -f deploy/ubuntu/docker-compose.dev.yml up -d
docker compose -f deploy/ubuntu/docker-compose.dev.yml logs -f

# Production
cd /srv/munney-prod
docker compose -f deploy/ubuntu/docker-compose.prod.yml up -d
docker compose -f deploy/ubuntu/docker-compose.prod.yml logs -f
```

## Symfony Console Commands

```bash
# Run inside backend container or prefix with:
# docker exec money-backend php bin/console [command]

# Cache
cache:clear
cache:warmup

# Database
doctrine:migrations:migrate
doctrine:migrations:status
doctrine:migrations:diff
make:migration

# Debug
debug:router
debug:container

# Users
# (custom commands in src/Command/)
```

## Git Commands

```bash
# Start new feature
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# Save work
git add -p
git commit -m "feat: description"

# Push and PR
git push -u origin feature/my-feature
# Create PR on GitHub: feature/* -> develop

# Release to production
# Create PR on GitHub: develop -> main
```

## API Endpoints (Key Routes)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/login | Authenticate user |
| GET | /api/accounts | List user accounts |
| POST | /api/accounts | Create account |
| GET | /api/transactions | List transactions |
| POST | /api/transactions/import | Import CSV |
| GET | /api/categories | List categories |
| GET | /api/budgets | List budgets |
| GET | /api/patterns | List patterns |
| GET | /api/savings-accounts | List savings |

## File Locations

```
# Backend
backend/src/                    # PHP source code
backend/config/                 # Symfony config
backend/migrations/             # Database migrations
backend/tests/                  # PHPUnit tests
backend/.env                    # Environment template

# Frontend
frontend/src/                   # React source code
frontend/src/domains/           # Feature modules
frontend/src/components/        # Shared components

# Docker
docker-compose.yml              # Local dev config
deploy/ubuntu/docker-compose.dev.yml    # Server dev
deploy/ubuntu/docker-compose.prod.yml   # Server prod

# CI/CD
.github/workflows/deploy-dev.yml
.github/workflows/deploy-prod.yml

# Environment
.env.local                      # Local secrets (gitignored)
.env.local.example              # Template for local
```

## Database Access

```bash
# Local MySQL CLI
docker exec -it money-mysql mysql -u money -p money_db
# Password: see .env.local

# External tools (local)
Host: localhost
Port: 3333
User: money
Database: money_db
```

## Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| Backend 500 error | `docker exec money-backend php bin/console cache:clear` |
| Permission denied | `docker exec money-backend chown -R www-data:www-data var/` |
| Migration failed | Check migration file, rollback: `doctrine:migrations:migrate prev` |
| Frontend not updating | Restart: `docker compose restart frontend` |
| Can't connect to DB | Check `docker compose ps`, ensure mysql is healthy |

## Environment Variables

### Required (Local)
```
MYSQL_ROOT_PASSWORD=xxx
MYSQL_PASSWORD=xxx
OPENAI_API_KEY=sk-xxx
JWT_PASSPHRASE=xxx
HCAPTCHA_SECRET_KEY=xxx
HCAPTCHA_SITE_KEY=xxx
```

### Development Server
Same but with `_DEV` suffix for DB passwords.

### Production Server
Same but with `_PROD` suffix for DB passwords, plus:
```
MAILER_DSN=xxx
MAIL_FROM_ADDRESS=xxx
APP_URL=xxx
```

## Branch → Environment Mapping

```
main     → Production (munney.munne.me)
develop  → Development (devmunney.home.munne.me)
feature/* → Local only (until PR merged to develop)
```

## Testing

```bash
# Backend tests
docker exec money-backend php bin/phpunit

# Specific test
docker exec money-backend php bin/phpunit --filter testName

# Frontend lint
cd frontend && npm run lint

# TypeScript check
cd frontend && npx tsc --noEmit
```
